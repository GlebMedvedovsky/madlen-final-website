<?php

namespace App\Data;

final readonly class ProjectPreviewSnapshot
{
    /**
     * @param  array{
     *     slug: string,
     *     title: array{de: string, en: string},
     *     category: string,
     *     cover: string,
     *     description: array{de: string, en: string},
     *     images: array<int, array{src: string, altDe: string, altEn: string, order: int, side: string}>
     * }  $manifestProject
     */
    public function __construct(
        public string $projectId,
        public string $slug,
        public int $position,
        public array $manifestProject,
    ) {}

    public function targetPath(): string
    {
        return 'portfolio/'.$this->slug;
    }
}
