<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\GeneratedPost;
use Illuminate\Support\Facades\Log;

class PostValidator
{
    /**
     * Validate a post before publishing.
     *
     * Hard errors (throws): missing image, image file not found.
     * Soft warnings (log only): title length, meta description length, word count.
     *
     * @throws ValidationException
     */
    public static function validate(GeneratedPost $post): void
    {
        // Título: 25-70 caracteres (warning)
        $titleLength = mb_strlen($post->title);
        if ($titleLength < 25 || $titleLength > 70) {
            Log::warning("PostValidator: title length out of range ({$titleLength} chars) for post #{$post->id} — \"{$post->title}\"");
        }

        // Meta description: 100-160 caracteres (warning)
        if (empty($post->meta_description)) {
            Log::warning("PostValidator: missing meta description for post #{$post->id}");
        } else {
            $metaLength = mb_strlen($post->meta_description);
            if ($metaLength < 100 || $metaLength > 160) {
                Log::warning("PostValidator: meta description length out of range ({$metaLength} chars) for post #{$post->id}");
            }
        }

        // Contenido mínimo: 300 palabras (warning)
        $wordCount = $post->word_count ?? str_word_count(strip_tags($post->content));
        if ($wordCount < 300) {
            Log::warning("PostValidator: low word count ({$wordCount} words) for post #{$post->id}");
        }

        // Imagen destacada ausente (error duro)
        if (empty($post->featured_image_url)) {
            throw new ValidationException("Se requiere una imagen destacada para publicar");
        }

        // Imagen destacada no existe en disco (error duro)
        $imagePath = str_replace(url('/'), '', $post->featured_image_url);
        $fullPath = public_path($imagePath);

        if (!file_exists($fullPath)) {
            throw new ValidationException(
                "La imagen destacada no existe en el path: {$fullPath}"
            );
        }
    }

    /**
     * Validate minimum quality score (hard error).
     *
     * @throws ValidationException
     */
    public static function validateQualityScore(GeneratedPost $post, int $minQuality = 70): void
    {
        if ($post->quality_score < $minQuality) {
            throw new ValidationException(
                "Quality score insuficiente: {$post->quality_score} (mínimo requerido: {$minQuality})"
            );
        }
    }
}
