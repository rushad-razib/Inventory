import styles from "./catalog.module.css";

const COLORS = ["#0f6b6b", "#1a1f24", "#c5892b", "#5b6570", "#3d7a6a", "#8c4a3a"];

export type ChartBar = {
  label: string;
  value: number;
  title: string;
  color: string;
};

export function chartColor(index: number): string {
  return COLORS[index % COLORS.length] ?? COLORS[0];
}

export type DemandPointMark = {
  date: string;
  value: number;
  title: string;
};

export type DemandSeries = {
  label: string;
  color: string;
  points: DemandPointMark[];
};

export function DemandChart({ series, empty }: { series: DemandSeries[]; empty: string }) {
  const marks = series.flatMap((line) => line.points);
  if (marks.length === 0) {
    return <p className={styles.muted}>{empty}</p>;
  }

  const dates = [...new Set(marks.map((mark) => mark.date))].sort();
  const max = Math.max(...marks.map((mark) => mark.value), 1);
  const whole = marks.every((mark) => Number.isInteger(mark.value));
  const padLeft = 48;
  const padRight = 16;
  const plotTop = 16;
  const plotBottom = 140;
  const width = Math.max(320, padLeft + padRight + Math.max(dates.length - 1, 1) * 64);
  const plotWidth = width - padLeft - padRight;

  const xFor = (date: string) => {
    if (dates.length === 1) {
      return padLeft + plotWidth / 2;
    }
    const index = dates.indexOf(date);
    return padLeft + (index / (dates.length - 1)) * plotWidth;
  };
  const yFor = (value: number) => plotBottom - (Math.max(value, 0) / max) * (plotBottom - plotTop);
  const scale = axisTicks(max, whole);

  return (
    <div className={styles.chartWrap}>
      <svg className={styles.chart} viewBox={`0 0 ${width} 172`} role="img" width="100%" height={172} style={{ minWidth: width }}>
        {scale.map((tick) => {
          const y = yFor(tick);
          return (
            <g key={tick}>
              <line x1={padLeft} x2={width - padRight} y1={y} y2={y} stroke="#ddd8d0" />
              <text x={padLeft - 8} y={y + 3} textAnchor="end" fontSize={10} fill="#5b6570" fontFamily="IBM Plex Mono, ui-monospace, monospace">
                {axisLabel(tick)}
              </text>
            </g>
          );
        })}
        {series.map((line) => {
          const ordered = [...line.points].sort((left, right) => left.date.localeCompare(right.date));
          const path = ordered.map((mark) => `${xFor(mark.date)},${yFor(mark.value)}`).join(" ");
          return (
            <g key={line.label}>
              {ordered.length > 1 ? (
                <polyline points={path} fill="none" stroke={line.color} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
              ) : null}
              {ordered.map((mark) => (
                <circle key={`${line.label}-${mark.date}`} cx={xFor(mark.date)} cy={yFor(mark.value)} r={3.5} fill={line.color}>
                  <title>{mark.title}</title>
                </circle>
              ))}
            </g>
          );
        })}
        {dates.map((date) => (
          <text key={date} x={xFor(date)} y={160} textAnchor="middle" fontSize={10} fill="#5b6570" fontFamily="IBM Plex Mono, ui-monospace, monospace">
            {date.slice(5)}
          </text>
        ))}
      </svg>
    </div>
  );
}

function axisTicks(max: number, whole: boolean): number[] {
  const mid = whole ? Math.round(max / 2) : max / 2;
  return [...new Set([0, mid, max])];
}

function axisLabel(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

export function RoiChart({ bars }: { bars: ChartBar[] }) {
  if (bars.length === 0) {
    return <p className={styles.muted}>No sales in this range.</p>;
  }

  const values = bars.map((bar) => bar.value);
  const min = Math.min(0, ...values);
  const max = Math.max(0, ...values);
  const span = Math.max(max - min, 0.0001);
  const plotTop = 16;
  const plotBottom = 148;
  const plotHeight = plotBottom - plotTop;
  const zeroY = plotTop + ((max - 0) / span) * plotHeight;
  const width = Math.max(320, bars.length * 56 + 24);

  return (
    <div className={styles.chartWrap}>
      <svg className={styles.chart} viewBox={`0 0 ${width} 190`} role="img" width={width} height={190}>
        <line x1={8} x2={width - 8} y1={zeroY} y2={zeroY} stroke="#ddd8d0" />
        {bars.map((bar, index) => {
          const barHeight = (Math.abs(bar.value) / span) * plotHeight;
          const x = 16 + index * 56;
          const y = bar.value >= 0 ? zeroY - barHeight : zeroY;
          return (
            <g key={`${bar.title}-${index}`}>
              <rect x={x} y={y} width={36} height={Math.max(barHeight, bar.value === 0 ? 0 : 1)} fill={bar.color} rx={2}>
                <title>{bar.title}</title>
              </rect>
              <text x={x + 18} y={172} textAnchor="middle" fontSize={10} fill="#5b6570" fontFamily="IBM Plex Mono, ui-monospace, monospace">
                {bar.label}
              </text>
            </g>
          );
        })}
      </svg>
    </div>
  );
}
