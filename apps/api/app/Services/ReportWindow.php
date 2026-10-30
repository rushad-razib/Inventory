<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class ReportWindow
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $group,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $today = CarbonImmutable::now((string) config('app.timezone'));
        $from = $request->input('from', $today->subDays(6)->toDateString());
        $to = $request->input('to', $today->toDateString());
        $group = $request->input('group', 'product');

        if (! is_string($from) || ! self::isDate($from)) {
            throw new InvalidArgumentException('from');
        }

        if (! is_string($to) || ! self::isDate($to)) {
            throw new InvalidArgumentException('to');
        }

        if ($from > $to) {
            throw new InvalidArgumentException('from');
        }

        if (! is_string($group) || ! in_array($group, ['product', 'category'], true)) {
            throw new InvalidArgumentException('group');
        }

        return new self($from, $to, $group);
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->from, (string) config('app.timezone'))->startOfDay();
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->to, (string) config('app.timezone'))->endOfDay();
    }

    /**
     * @return array<string, string>
     */
    public function meta(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'group' => $this->group,
        ];
    }

    private static function isDate(string $value): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $value;
    }
}
