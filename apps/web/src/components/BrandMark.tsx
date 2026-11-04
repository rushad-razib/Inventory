import { brandingLabel, useBranding } from "../lib/branding";

type BrandMarkProps = {
  className?: string;
  logoClassName?: string;
};

export function BrandMark({ className, logoClassName }: BrandMarkProps) {
  const { data } = useBranding();
  const label = brandingLabel(data);

  return (
    <div className={className}>
      {data?.logo_url ? <img className={logoClassName} src={data.logo_url} alt="" /> : null}
      <span>{label}</span>
    </div>
  );
}
