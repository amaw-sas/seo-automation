<?php

namespace App\Services;

/**
 * DTO for publish operation result.
 */
class PublishResult
{
    public function __construct(
        public bool $success,
        public int $wordpressPostId,
        public string $publishedUrl,
        public float $duration,
        public int $imagesUploaded = 0
    ) {
    }
}
