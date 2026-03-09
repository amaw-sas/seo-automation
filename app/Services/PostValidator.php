<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\GeneratedPost;

class PostValidator
{
    /**
     * Valida un post antes de publicación según criterios SEO básicos.
     *
     * @param GeneratedPost $post
     * @return void
     * @throws ValidationException
     */
    public static function validate(GeneratedPost $post): void
    {
        // Validar título: 30-60 caracteres
        $titleLength = mb_strlen($post->title);
        if ($titleLength < 30 || $titleLength > 60) {
            throw new ValidationException(
                "El título debe tener entre 30 y 60 caracteres (actual: {$titleLength})"
            );
        }

        // Validar meta description: 100-160 caracteres
        if (empty($post->meta_description)) {
            throw new ValidationException("Meta description es requerida");
        }

        $metaLength = mb_strlen($post->meta_description);
        if ($metaLength < 100 || $metaLength > 160) {
            throw new ValidationException(
                "Meta description debe tener entre 100 y 160 caracteres (actual: {$metaLength})"
            );
        }

        // Validar contenido mínimo: 300 palabras
        $wordCount = $post->word_count ?? str_word_count(strip_tags($post->content));
        if ($wordCount < 300) {
            throw new ValidationException(
                "El contenido debe tener mínimo 300 palabras (actual: {$wordCount})"
            );
        }

        // Validar imagen destacada
        if (empty($post->featured_image_url)) {
            throw new ValidationException("Se requiere una imagen destacada para publicar");
        }

        // Validar que la imagen destacada exista localmente
        $imagePath = str_replace(url('/'), '', $post->featured_image_url);
        $fullPath = public_path($imagePath);

        if (!file_exists($fullPath)) {
            throw new ValidationException(
                "La imagen destacada no existe en el path: {$fullPath}"
            );
        }
    }

    /**
     * Valida que el post cumpla con un quality score mínimo.
     *
     * @param GeneratedPost $post
     * @param int $minQuality
     * @return void
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
