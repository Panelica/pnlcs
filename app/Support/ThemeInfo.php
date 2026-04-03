<?php

namespace App\Support;

class ThemeInfo
{
    public string $slug;
    public string $name;
    public string $version;
    public string $author;
    public string $description;
    public string $screenshot;
    public array $colors;
    public array $dark_colors;
    public array $fonts;
    public array $supports;
    public string $path;
    public bool $isActive;
    public bool $isBuiltin;

    public static function fromJson(string $slug, string $path, array $json, bool $isActive): self
    {
        $info = new self();
        $info->slug = $slug;
        $info->name = $json['name'] ?? ucfirst($slug);
        $info->version = $json['version'] ?? '1.0.0';
        $info->author = $json['author'] ?? 'Unknown';
        $info->description = $json['description'] ?? '';
        $info->colors = $json['colors'] ?? [];
        $info->dark_colors = $json['dark_colors'] ?? [];
        $info->fonts = $json['fonts'] ?? [];
        $info->supports = $json['supports'] ?? [];
        $info->path = $path;
        $info->isActive = $isActive;
        $info->isBuiltin = in_array($slug, ['panelica','starter','flavor','aurora','ember','ocean','forest','midnight','sunset','arctic','terra','neon','coral','slate','royal','mint']);

        // Screenshot URL
        $screenshotFile = $json['screenshot'] ?? 'screenshot.png';
        if (file_exists($path . '/' . $screenshotFile)) {
            $info->screenshot = '/themes/' . $slug . '/' . $screenshotFile;
        } else {
            $info->screenshot = '';
        }

        return $info;
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'version' => $this->version,
            'author' => $this->author,
            'description' => $this->description,
            'screenshot' => $this->screenshot,
            'colors' => $this->colors,
            'dark_colors' => $this->dark_colors,
            'fonts' => $this->fonts,
            'supports' => $this->supports,
            'isActive' => $this->isActive,
            'isBuiltin' => $this->isBuiltin,
        ];
    }
}
