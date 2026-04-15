<?php

declare(strict_types=1);

namespace App\Platform\ApiContract\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OpsGateArtifactService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function writeReport(
        string $artifactRoot,
        string $reportPrefix,
        string $scopeKey,
        array $payload,
        string $markdown,
        ?Carbon $evaluatedAt = null,
        string $artifactKey = 'artifacts',
    ): array {
        $normalizedArtifactRoot = trim($artifactRoot, " \t\n\r\0\x0B/");
        if ($normalizedArtifactRoot === '') {
            throw new InvalidArgumentException('Artifact root must not be empty.');
        }

        $normalizedReportPrefix = Str::slug($reportPrefix, '-');
        if ($normalizedReportPrefix === '') {
            throw new InvalidArgumentException('Report prefix must not be empty.');
        }

        $normalizedScopeKey = trim($scopeKey);
        $scopeSlug = Str::slug($normalizedScopeKey, '-');
        if ($scopeSlug === '') {
            $scopeSlug = 'default';
            $normalizedScopeKey = 'default';
        }

        $evaluatedAt ??= now('UTC');
        $reportsRelativePath = trim($normalizedArtifactRoot.'/reports', '/');
        $reportsAbsolutePath = base_path($reportsRelativePath);
        File::ensureDirectoryExists($reportsAbsolutePath);

        $timestamp = $evaluatedAt->copy()->utc()->format('Ymd\THis\Z');
        $baseName = sprintf('%s-%s-%s', $normalizedReportPrefix, $scopeSlug, strtolower($timestamp));

        $jsonRelativePath = $reportsRelativePath.'/'.$baseName.'.json';
        $markdownRelativePath = $reportsRelativePath.'/'.$baseName.'.md';
        $latestJsonRelativePath = $reportsRelativePath.'/latest-'.$scopeSlug.'.json';
        $latestMarkdownRelativePath = $reportsRelativePath.'/latest-'.$scopeSlug.'.md';

        $payload[$artifactKey] = array_merge((array) ($payload[$artifactKey] ?? []), [
            'root' => $normalizedArtifactRoot,
            'reports_root' => $reportsRelativePath,
            'report_prefix' => $normalizedReportPrefix,
            'scope_key' => $normalizedScopeKey,
            'scope_slug' => $scopeSlug,
            'json_path' => $jsonRelativePath,
            'markdown_path' => $markdownRelativePath,
            'latest_json_path' => $latestJsonRelativePath,
            'latest_markdown_path' => $latestMarkdownRelativePath,
        ]);

        $jsonContents = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonContents === false) {
            throw new InvalidArgumentException('Failed to encode artifact payload as JSON.');
        }

        File::put(base_path($jsonRelativePath), $jsonContents.PHP_EOL);
        File::copy(base_path($jsonRelativePath), base_path($latestJsonRelativePath));

        $normalizedMarkdown = rtrim($markdown);
        File::put(base_path($markdownRelativePath), $normalizedMarkdown.PHP_EOL);
        File::copy(base_path($markdownRelativePath), base_path($latestMarkdownRelativePath));

        return $payload;
    }
}
