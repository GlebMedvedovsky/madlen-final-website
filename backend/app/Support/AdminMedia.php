<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Support\HtmlString;

class AdminMedia
{
    public static function url(?MediaAsset $asset): ?string
    {
        return $asset ? route('admin.media', ['mediaAsset' => $asset]) : null;
    }

    public static function option(MediaAsset $asset): string
    {
        $name = e($asset->original_name);
        $meta = e($asset->width && $asset->height ? "{$asset->width} × {$asset->height}" : strtoupper($asset->kind));

        if ($asset->kind !== 'image') {
            return "<span class=\"madlen-media-option\"><span class=\"madlen-media-option__video\">▶</span><span><strong>{$name}</strong><small>{$meta}</small></span></span>";
        }

        $url = e((string) self::url($asset));

        return "<span class=\"madlen-media-option\"><img src=\"{$url}\" alt=\"\" loading=\"lazy\"><span><strong>{$name}</strong><small>{$meta}</small></span></span>";
    }

    public static function preview(?MediaAsset $asset): HtmlString
    {
        if (! $asset) {
            return new HtmlString('<span>Kein Medium ausgewählt.</span>');
        }

        $url = e((string) self::url($asset));
        $name = e($asset->original_name);
        if ($asset->kind === 'video') {
            return new HtmlString("<div class=\"madlen-media-preview\"><video src=\"{$url}\" controls preload=\"metadata\"></video><strong>{$name}</strong></div>");
        }

        return new HtmlString("<div class=\"madlen-media-preview\"><img src=\"{$url}\" alt=\"\"><strong>{$name}</strong></div>");
    }
}
