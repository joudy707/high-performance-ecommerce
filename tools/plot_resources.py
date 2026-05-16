import argparse
import pandas as pd
import matplotlib.pyplot as plt

parser = argparse.ArgumentParser(description="Plot app/system CPU and memory from monitor-v2.ps1 CSV output.")
parser.add_argument("csv_file")
parser.add_argument("--title", default="Resource usage")
parser.add_argument("--out", default="resources.png")
args = parser.parse_args()

df = pd.read_csv(args.csv_file)
df["timestamp"] = pd.to_datetime(df["timestamp"])
df["seconds"] = (df["timestamp"] - df["timestamp"].iloc[0]).dt.total_seconds()

fig, ax1 = plt.subplots(figsize=(12, 5), dpi=140)
ax1.plot(df["seconds"], df["total_app_cpu"], label="App CPU %")
ax1.plot(df["seconds"], df["system_cpu"], label="System CPU %")
ax1.set_xlabel("Seconds")
ax1.set_ylabel("CPU %")
ax1.legend(loc="upper left")

ax2 = ax1.twinx()
ax2.plot(df["seconds"], df["app_memory_mb"], linestyle="--", label="App memory MB")
ax2.set_ylabel("Memory MB")
ax2.legend(loc="upper right")

plt.title(args.title)
plt.tight_layout()
plt.savefig(args.out)
print(f"Saved chart to {args.out}")
