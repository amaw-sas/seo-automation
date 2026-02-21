<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SEO Automation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para el sistema de automatización SEO
    |
    */

    /**
     * Directorio base donde están los datos de SEMRush
     */
    'semrush_data_dir' => env('SEMRUSH_DATA_DIR', base_path('../semrushdiego')),

    /**
     * Dominios propios
     */
    'own_domains' => [
        'alquilatucarro.com.co',
        'alquilame.com.co',
        'alquicarros.co',
    ],

    /**
     * Competidores
     */
    'competitor_domains' => [
        'alkilautos.com',
        'autoalquilados.com',
        'avis.com.co',
        'despegar.com.co',
        'equirent.com.co',
        'evolutionrentacar.com.co',
        'executiverentacar.com',
        'gorentacar.com',
        'hertz.com.co',
        'kayak.com.co',
        'localiza.com',
        'rentalcars.com',
        'rentingcolombia.com',
    ],

    /**
     * Configuración de importación
     */
    'import' => [
        'batch_size' => 1000,
        'chunk_size' => 500,
        'max_execution_time' => 3600, // 1 hora
    ],

    /**
     * Configuración de calidad de backlinks
     */
    'backlink_quality' => [
        'spam_patterns' => [
            'rankvance',
            'backlinksolutions',
            'seoauthority.online',
            'linkbuilding.guru',
        ],
        'min_authority_score' => 20,
        'min_quality_score' => 3,
    ],

    /**
     * Configuración de keyword opportunities
     */
    'keyword_opportunities' => [
        'max_difficulty' => 30,
        'min_volume' => 100,
        'min_opportunity_score' => 50,
    ],

    /**
     * Fechas de snapshot por defecto
     */
    'default_snapshot_date' => env('SEO_DEFAULT_SNAPSHOT_DATE', now()->format('Y-m-d')),

    /**
     * Debug mode
     */
    'debug' => env('SEO_DEBUG', false),

];
