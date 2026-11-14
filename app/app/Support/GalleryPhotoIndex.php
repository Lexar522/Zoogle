<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Шляхи галереї без повторів: і за шляхом, і за вмістом файлу.
 * Вміст порівнюється лише для файлів однакового розміру, тож зайвого читання немає.
 */
final class GalleryPhotoIndex
{
    /** @var list<string> */
    private array $paths = [];

    /** @var array<int, list<string>> розмір файлу → шляхи */
    private array $pathsBySize = [];

    /** @var array<string, string> */
    private array $hashes = [];

    public function __construct(private readonly Filesystem $disk) {}

    /**
     * Додає фото і повертає шлях, під яким воно є в галереї (наявний — якщо це копія).
     */
    public function add(string $path): string
    {
        if (in_array($path, $this->paths, true)) {
            return $path;
        }

        $size = $this->disk->exists($path) ? $this->disk->size($path) : null;
        foreach ($size === null ? [] : ($this->pathsBySize[$size] ?? []) as $existing) {
            if ($this->hash($existing) === $this->hash($path)) {
                return $existing;
            }
        }

        $this->paths[] = $path;
        if ($size !== null) {
            $this->pathsBySize[$size][] = $path;
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    private function hash(string $path): string
    {
        return $this->hashes[$path] ??= md5((string) $this->disk->get($path));
    }
}
