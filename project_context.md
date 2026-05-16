# High-Performance E-Commerce Backend Engine
## Parallel Programming Course Project — 2026

---

## Project Overview

The goal is to build a complete e-commerce backend system that handles **thousands of concurrent requests**. The focus is **not** on the number of features or pages, but on applying **non-functional requirements** (performance, data integrity, resource management, background automation) studied across 8 lectures — ensuring the system stays stable under high load.

---

## Functional Requirements (Student-Designed)

Students have freedom to design the core e-commerce features, as long as they are sufficient to implement the non-functional requirements below:

1. **User Authentication** — Register & Login
2. **Product Management** — List products, product details, stock quantity (can be seeded)
3. **Order Flow** — Add to cart with quantity, view cart with total price, confirm order
4. **Payment** — Payment confirmation
5. **Notifications** — Send notification/email after order
6. **Daily Sales Report** — Aggregate daily sales totals (`Daily Aggregation Sales`)

### Minimal Golden Flow (Happy Path)
1. User requests a product
2. System checks stock
3. Creates order
4. Dispatches background jobs: invoice, notification
5. Later (batch job): calculates daily sales

---

## Non-Functional Requirements (The Core of the Project)

### 1. Concurrent Access & Data Integrity
**Goal:** Allow multiple users to modify the same resource (e.g., product stock) simultaneously without conflicts. Must prove that the **Race Condition** problem is handled.

**Before optimization (naive approach):**
```
Each request → reads quantity → decides if stock exists → decrements → saves
```
Problem: Two users both see `stock = 10`, both decrement, resulting in incorrect stock.

**After optimization — two strategies:**

**Strategy A: Database-Level Locking (Pessimistic Locking)**
- User A acquires lock → User B blocks (waits)
- After A finishes, B reads the updated value (e.g., 9)
- Drawback: Under high load, locks become a bottleneck

**Strategy B: Atomic Update (Better practical performance)**
```sql
UPDATE products SET stock = stock - 1 WHERE id = ? AND stock > 0
```
- Database verifies atomically: only decrements if `stock > 0`
- Entire operation is atomic — no race condition, no heavy locks

---

### 2. Resource Management & Capacity Control
**Goal:** Control the number of parallel operations so the system neither collapses (resource overuse) nor slows down (resource underuse).

**Before optimization:**
- Each request spawns a new process/thread
- Each opens a DB connection, executes queries, may call external APIs
- Under load: CPU hits 100%, RAM fills, DB connections exhausted → cascade failure

**After optimization — layered control:**

**Layer 1: Rate Limiting** *(outside lectures — entry-point control)*
- Limit requests per user / IP / API key

**Layer 2: Bounded Concurrency** *(limit parallel operations)*
- Max 100 requests processed simultaneously
- Excess requests wait or are rejected
- Implementation: Worker Pool + Semaphore/Token system

**Layer 3: Queue-Based Architecture** *(most important)*
- Request arrives → registered → placed in Queue
- Fixed number of Workers process jobs from the queue

**Layer 4: Connection Pooling** *(outside lectures)*
- Same principle as Thread Pool but for DB connections
- Limit and reuse DB connections instead of opening new ones per request

**Laravel-specific decisions:**
- Rate limiting (middleware)
- Queue (Redis + workers)
- Limit number of workers
- Laravel Horizon (monitoring)
- DB connection limits
- Load balancer (if scaling)

---

### 3. Asynchronous Processing (Queues)
**Goal:** Move tasks the user doesn't need to wait for (invoices, notifications) outside the main request path.

**Before optimization (Synchronous/Blocking):**
```
User clicks "Place Order" →
  Save order (sync)
  Deduct stock (sync)
  Generate invoice PDF (sync)
  Send email (sync)         ← user waits for ALL of this
  Send notification (sync)
→ Response returned after ~8 seconds
```
If any step fails → entire order fails.

**After optimization (Async):**

**Critical Path (must happen immediately):**
- Create order
- Confirm payment (if synchronous)
- Deduct stock

**Background Tasks (user doesn't need to wait):**
- Generate invoice
- Send email
- Send notifications
- Logging / analytics

**New Flow:**
1. User clicks "Place Order"
2. Server executes critical operations only
3. Queues background jobs: `Generate Invoice`, `Send Email`, `Send Notification`
4. Returns response immediately (~100–200ms)
5. Workers pull jobs from queue and execute them in background

**Components:**
- Queue system (Redis / RabbitMQ) — stores jobs
- Workers (limited count) — consume jobs gradually
- Retry mechanism — if a job fails, it's retried automatically (exponential backoff)
- Dead Letter Queue (DLQ) — after N failed retries, job moves here for manual inspection ("poison messages")

---

### 4. Batch Processing (Large Data)
**Goal:** Write a background job that calculates daily sales and processes data in **chunks** for better performance.

**Before optimization:**
- Single job loads ALL day's data at once
- High RAM usage → possible Out Of Memory crash
- If it fails, must restart from scratch

**After optimization — Chunked Processing:**

**Execution Flow:**
1. Background job starts (e.g., "Daily Sales Report")
2. Sets batch size (e.g., 1000 records)
3. Loop:
   - Fetch 1000 records only
   - Process them
   - Store partial result
   - Free memory
   - Move to next batch

**Additional optimization — Parallel Jobs:**
Split work into multiple parallel jobs instead of one sequential job:
```
Job 1: records 1–1000
Job 2: records 1001–2000
Job 3: records 2001–3000
...
```

**Checkpointing (very important):**
- Store the last processed chunk
- If job fails → resume from that point, not from the beginning

**Why it matters (Bank Statement example):**
- 10 million statements needed on the 1st of each month
- Sequential (1 sec/statement): **115 days** — commercially unacceptable
- Parallel (100 chunks × 100 threads): **~27 hours** — completable over a weekend

---

### 5. Load Distribution
**Goal:** Simulate distributing requests across multiple servers with a justified load balancing strategy.

**Before optimization:**
- All requests go to a single server (one Laravel instance + one DB)

**After optimization:**

**New Architecture (High-Level):**
```
Users
  ↓
Load Balancer
  ↓
[App Server 1] [App Server 2] [App Server 3]
     ↓               ↓               ↓
      Shared DB / Cache / Queue
```

**Step 1: Load Balancer**
- Receives all requests
- Distributes based on a strategy (Round Robin, Least Connections, IP Hash, etc.)
- Monitors server health

**Step 2: Stateless Application**
- Problem: server stores user session in local memory → can't distribute
- Solution: Sessions in Redis (shared storage) — servers become stateless

**Step 3: Database Layer Optimization**
- Read Replicas: one server for writes, multiple servers for reads
- Query optimization

**Step 4: Health Checks**
- If a server goes down → remove it from the pool automatically
- Result: high availability

**Step 5: Auto Scaling**
- High load → add servers
- Low load → reduce servers

---

### 6. Distributed Caching (Caching Strategy)
**Goal:** Integrate a caching layer (e.g., Redis) to store frequently requested products and reduce direct DB queries.

---

### 7. Concurrency Control (Locking)
**Goal:** Apply Optimistic Locking or Pessimistic Locking when updating sensitive stock quantities.

*(See section 1 above for full detail — strategies A and B)*

---

### 8. Transaction Integrity (ACID / Transaction Safety)
**Goal:** Ensure compound operations (payment + stock update + order creation) either **all succeed or all fail**, even under concurrent access.

---

### 9. Stress Testing
**Goal:** Provide a report proving the system can serve **at least 100 concurrent users** without crashing or losing data.

---

### 10. Benchmarking & Bottleneck Analysis
**Goal:** Measure response time for key operations, identify at least one bottleneck, and present a **before vs. after numeric comparison**.

---

## My Role (What You Need to Help Me With)

I am working on **Role 3 — Asynchronous Processing Engineer** (`feature/async` branch). This is my only responsibility in the project. Do not suggest or implement anything outside this scope — other roles are handled by teammates on separate branches.

My job is to:
- Move non-critical tasks (invoice generation, email, notifications) **out of the main request path**
- Implement a **Queue system** using Redis
- Write **background jobs** (Laravel Jobs)
- Handle **retry logic** and **Dead Letter Queue** for failed jobs
- Show a clear before/after: synchronous blocking request vs. async response in ~100–200ms

Everything else (stock locking, rate limiting, batch processing, load balancing) is someone else's branch. Stay focused on async processing only.

and : [5/8/2026 9:04 PM] Joudy: ومشان يكون شغلنا موحد كل حدا يعمل شغلو مرتين مرة بتابع يسميه يلي بدو مع Broken ومرة بالشكل المحسن بتابع تاني مع كلمة Fixed . 

---

## Team Structure (5 Roles)

| # | Role | Responsibilities | Works On |
|---|------|-----------------|----------|
| 1 | **Data Integrity & Concurrency Engineer** | Race conditions, stock consistency, transactions | Order creation flow, stock update logic |
| 2 | **Performance & Resource Control Engineer** | Rate limiting, operation count control, overload prevention | API entry layer, request handling |
| 3 | **Asynchronous Processing Engineer** | Queues, background jobs, retry logic | Invoice, notifications |
| 4 | **Batch & Data Processing Engineer** | Batch processing, chunking, aggregations | Daily sales report |
| 5 | **System Scaling & Load Distribution Engineer** | Load balancing, multi-server architecture, stateless design | Request distribution, system architecture |

---

## GitHub Branch Structure

```
baseline                    ← base system (no optimizations)
feature/concurrency         ← Role 1: data integrity & locking
feature/resource            ← Role 2: rate limiting & resource control
feature/async               ← Role 3: queues & async processing
feature/batch               ← Role 4: batch processing & chunking
feature/load                ← Role 5: load balancing & scaling
```

Each branch represents one engineer's area. Baseline is the unoptimized starting point. Each feature branch adds its optimization layer. All branches eventually merge to form the complete optimized system.

---

## Project Timeline

| Week | Focus |
|------|-------|
| Week 1 | Build the base system (whole team) |
| Week 2 | Each person starts their optimization |
| Week 3 | Merge all optimizations + load testing |

**Deliverables:**
- Architecture documentation explaining design decisions and how AOP is used for performance monitoring
- Clean source code with comments marking synchronization points and thread-safe concurrency
- Demo sessions (multiple evaluation rounds) simulating high load while monitoring thread and DB behavior

**Available Frameworks:** Laravel, Spring Boot, .NET, Django

---

## Key Concepts Summary (From Lectures)

| Concept | Lecture | Applied In |
|---------|---------|-----------|
| Locks (Acquire → Process → Release) | Lecture 1 | Stock updates (pessimistic locking) |
| Thread Pool | Lecture 2 | Worker pools for request handling |
| Queue-Based Architecture | Lecture 2 | Async job processing |
| Async vs Sync Processing | Lecture 3 | Invoice/notification offloading |
| Retry & Dead Letter Queue | Lecture 3 | Failed job handling |
| Batch Processing / Chunking | Lecture 4 | Daily sales report |
| Parallel Batch Jobs | Lecture 4 | Partitioning + distributing chunks across workers |

**Golden Rule on Locks:** Any lock that is acquired **must** be released — otherwise the resource stays locked forever.

**Atomic Updates > Locks** for stock management: doing the entire decrement inside a single DB query eliminates the need for heavy application-level locking and is faster under high concurrency.
 
