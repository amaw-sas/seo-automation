<?php

namespace App\Console\Commands\Seo;

use App\Models\Keyword;
use App\Models\TopicResearch;
use App\Services\ContentGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GeneratePost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:generate:post
                            {--keyword= : Keyword ID or text to generate post for}
                            {--topic= : Topic research ID to generate post for}
                            {--llm=openai : LLM provider to use (anthropic, openai, google, xai)}
                            {--image-llm= : Image LLM provider to use (xai, dalle3, stable-diffusion)}
                            {--dry-run : Generate outline only, do not create post}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar un post de blog usando LLM';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generando post de blog con LLM...');
        $this->newLine();

        // Determine source
        $source = $this->getSource();

        if (!$source) {
            $this->error('Debes especificar --keyword o --topic');
            return Command::FAILURE;
        }

        // Display source info
        if ($source instanceof TopicResearch) {
            $this->line("Topic: {$source->title}");
            $this->line("Ciudad: " . ($source->city?->name ?? 'Colombia'));
            $this->line("Tráfico potencial: {$source->potential_traffic}");
        } else {
            $this->line("Keyword: {$source->keyword}");
            $this->line("Ciudad: " . ($source->city?->name ?? 'Colombia'));
            $this->line("Volumen: {$source->search_volume_co}");
        }

        $this->newLine();

        // Check LLM provider
        $llmProvider = $this->option('llm');
        $this->line("LLM Provider: {$llmProvider}");

        // Check Image LLM provider
        $imageLLMProvider = $this->option('image-llm');
        if ($imageLLMProvider) {
            $this->line("Image LLM Provider: {$imageLLMProvider}");
        }

        // Create generator
        try {
            $generator = new ContentGenerator($llmProvider, $imageLLMProvider);
        } catch (\Exception $e) {
            $this->error("Error al inicializar LLM provider: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Generate post
        try {
            $this->info('Generando contenido...');
            $post = $generator->generatePost($source);

            $this->newLine();
            $this->info('✓ Post generado exitosamente');
            $this->newLine();

            // Build results table
            $results = [
                ['ID', $post->id],
                ['Título', $post->title],
                ['Palabras', $post->word_count],
                ['Quality Score', $post->quality_score . '/100'],
                ['Tiempo de lectura', $post->reading_time_minutes . ' min'],
                ['Tokens (input)', number_format($post->llm_prompt_tokens)],
                ['Tokens (output)', number_format($post->llm_completion_tokens)],
                ['Costo LLM', '$' . number_format($post->llm_cost_usd, 4)],
            ];

            // Add image info if generated
            if ($post->featured_image_url) {
                $results[] = ['Imagen destacada', '✓ Generada'];
                $results[] = ['Imágenes inline', count($post->inline_images ?? [])];
                $results[] = ['Costo imágenes', '$' . number_format($post->image_generation_cost_usd, 4)];
                $results[] = ['Costo total', '$' . number_format($post->total_cost, 4)];
            }

            $results[] = ['Estado', $post->status];

            // Display results
            $this->table(['Métrica', 'Valor'], $results);

            $this->newLine();
            $this->line('Preview del contenido (primeros 500 caracteres):');
            $this->line(str_repeat('-', 80));
            $this->line(Str::limit(strip_tags($post->content), 500));
            $this->line(str_repeat('-', 80));

            $this->newLine();
            $this->info("Para ver el post completo:");
            $this->line("php artisan tinker");
            $this->line("\$post = \\App\\Models\\GeneratedPost::find({$post->id});");
            $this->line("echo \$post->content;");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error al generar post:');
            $this->error($e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Get source (keyword or topic)
     */
    private function getSource()
    {
        if ($this->option('topic')) {
            $topicId = $this->option('topic');
            return TopicResearch::find($topicId);
        }

        if ($this->option('keyword')) {
            $keywordInput = $this->option('keyword');

            // Try as ID first
            if (is_numeric($keywordInput)) {
                $keyword = Keyword::find($keywordInput);
                if ($keyword) {
                    return $keyword;
                }
            }

            // Try as text
            return Keyword::where('keyword', 'LIKE', '%' . $keywordInput . '%')->first();
        }

        return null;
    }
}
