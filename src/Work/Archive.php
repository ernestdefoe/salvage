<?php

namespace Ernestdefoe\Salvage\Work;

use Carbon\Carbon;
use Ernestdefoe\Salvage\Link;
use Ernestdefoe\Salvage\Net\Fetcher;
use Ernestdefoe\Salvage\Net\Refused;

/**
 * Asks the Wayback Machine for its closest copy of a broken link.
 */
class Archive
{
    public const HOST = 'archive.org';
    public const API = 'https://archive.org/wayback/available?url=';

    public function __construct(private readonly Fetcher $fetcher)
    {
    }

    /**
     * @return int requests spent
     */
    public function lookup(Link $link): int
    {
        try {
            $response = $this->fetcher->fetch('GET', self::API.rawurlencode($link->url), 64 * 1024);
        } catch (Refused) {
            // Asked again on a later run; archive.org has bad days too.
            return 1;
        }

        if ($response->status === 200) {
            $data = json_decode($response->body, true);
            $closest = is_array($data) ? ($data['archived_snapshots']['closest'] ?? null) : null;

            $link->archive_url = is_array($closest) && ($closest['available'] ?? false) === true
                ? self::snapshotUrl((string) ($closest['url'] ?? ''))
                : null;
            $link->archive_checked_at = Carbon::now();
            $link->save();
        }

        return 1;
    }

    /**
     * Only a Wayback snapshot address is ever stored, and always as https.
     */
    public static function snapshotUrl(string $url): ?string
    {
        if (preg_match('~^https?://web\.archive\.org/web/(\d{1,14})/(https?://[^\s"<>]+)$~i', $url, $m) !== 1 || strlen($url) > 2048) {
            return null;
        }

        return "https://web.archive.org/web/$m[1]/$m[2]";
    }
}
