<?php

declare(strict_types=1);

namespace Portfolio\IcsCalendarFeed;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Builds an RFC 5545 VCALENDAR feed that real calendar clients (Outlook,
 * Google, Apple) accept, including a VTIMEZONE block with an explicit TZID so
 * events render at the correct local time across daylight-saving changes.
 *
 * An event is an associative array:
 *   [
 *     'uid'     => string,             // stable unique id
 *     'summary' => string,             // title
 *     'start'   => DateTimeInterface,  // local time in $tzid
 *     'end'     => DateTimeInterface,  // local time in $tzid
 *     'location'=> string (optional),
 *     'description' => string (optional),
 *   ]
 */
final class IcsFeedBuilder
{
    public function __construct(
        private string $tzid = 'Europe/Amsterdam',
        private string $calendarName = 'My Calendar',
        private string $prodId = '-//Portfolio//ICS Calendar Feed//EN'
    ) {
    }

    /** @param array<int,array<string,mixed>> $events */
    public function build(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . $this->prodId,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($this->calendarName),
            'X-WR-TIMEZONE:' . $this->tzid,
        ];

        $lines = array_merge($lines, $this->timezoneBlock());

        $stamp = (new DateTimeImmutable('now'))->format('Ymd\THis\Z');
        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $this->escapeText((string)$event['uid']);
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'DTSTART;TZID=' . $this->tzid . ':' . $this->localStamp($event['start']);
            $lines[] = 'DTEND;TZID=' . $this->tzid . ':' . $this->localStamp($event['end']);
            $lines[] = 'SUMMARY:' . $this->escapeText((string)($event['summary'] ?? ''));
            if (!empty($event['location'])) {
                $lines[] = 'LOCATION:' . $this->escapeText((string)$event['location']);
            }
            if (!empty($event['description'])) {
                $lines[] = 'DESCRIPTION:' . $this->escapeText((string)$event['description']);
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545 requires CRLF line endings, and lines folded at 75 octets.
        return implode("\r\n", array_map([$this, 'foldLine'], $lines)) . "\r\n";
    }

    /**
     * A minimal but valid VTIMEZONE for Central European Time. Static rules are
     * sufficient for the common European zones and keep the feed self-contained.
     */
    private function timezoneBlock(): array
    {
        return [
            'BEGIN:VTIMEZONE',
            'TZID:' . $this->tzid,
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    private function localStamp(DateTimeInterface $dt): string
    {
        return $dt->format('Ymd\THis');
    }

    private function escapeText(string $text): string
    {
        // Escape per RFC 5545 section 3.3.11.
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(["\r\n", "\n", "\r"], '\\n', $text);
        $text = str_replace([',', ';'], ['\\,', '\\;'], $text);
        return $text;
    }

    /** Folds a content line to 75 octets with a leading space on continuations. */
    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $folded = '';
        $chunk  = 75;
        while (strlen($line) > $chunk) {
            $folded .= substr($line, 0, $chunk) . "\r\n ";
            $line = substr($line, $chunk);
            $chunk = 74; // account for the leading space on folded lines
        }
        return $folded . $line;
    }
}
