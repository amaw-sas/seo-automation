<?php

namespace App\Services\Importers;

use App\Models\KeywordRanking;
use App\Models\Keyword;
use App\Models\Domain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RankingImporter
{
    /**
     * Import or update keyword ranking
     *
     * @param array $data
     * @return KeywordRanking
     */
    public function importRanking(array $data): KeywordRanking
    {
        // Calcular snapshot_month si no está presente
        if (isset($data['snapshot_date']) && !isset($data['snapshot_month'])) {
            $date = is_string($data['snapshot_date'])
                ? Carbon::parse($data['snapshot_date'])
                : $data['snapshot_date'];

            $data['snapshot_month'] = $date->format('Y-m');
        }

        // Usar upsert para evitar duplicados
        $ranking = KeywordRanking::updateOrCreate(
            [
                'keyword_id' => $data['keyword_id'],
                'domain_id' => $data['domain_id'],
                'snapshot_date' => $data['snapshot_date'],
            ],
            [
                'position' => $data['position'],
                'url' => $data['url'] ?? null,
                'estimated_traffic' => $data['estimated_traffic'] ?? null,
                'snapshot_month' => $data['snapshot_month'],
            ]
        );

        return $ranking;
    }

    /**
     * Find or create keyword by name
     *
     * @param string $keywordName
     * @param int|null $cityId
     * @return Keyword|null
     */
    public function findOrCreateKeyword(string $keywordName, ?int $cityId = null): ?Keyword
    {
        $normalized = Keyword::normalize($keywordName);

        // Buscar keyword existente
        $keyword = Keyword::where('keyword_normalized', $normalized)
            ->where(function($query) use ($cityId) {
                if ($cityId) {
                    $query->where('city_id', $cityId);
                } else {
                    $query->whereNull('city_id');
                }
            })
            ->first();

        // Si no existe, crear básica
        if (!$keyword) {
            $keyword = Keyword::create([
                'keyword' => $keywordName,
                'keyword_normalized' => $normalized,
                'city_id' => $cityId,
                'search_volume_co' => 0, // Se actualizará después con datos reales
            ]);
        }

        return $keyword;
    }

    /**
     * Import rankings for a domain from array
     *
     * @param int $domainId
     * @param array $rankings Array of rankings data
     * @param string $snapshotDate Date for this snapshot
     * @return int Count of imported rankings
     */
    public function importDomainRankings(int $domainId, array $rankings, string $snapshotDate): int
    {
        $count = 0;

        DB::transaction(function () use ($domainId, $rankings, $snapshotDate, &$count) {
            foreach ($rankings as $rankingData) {
                // Asegurar que tenga domain_id y snapshot_date
                $rankingData['domain_id'] = $domainId;
                $rankingData['snapshot_date'] = $snapshotDate;

                $this->importRanking($rankingData);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Get latest snapshot date for a domain
     *
     * @param int $domainId
     * @return string|null
     */
    public function getLatestSnapshotDate(int $domainId): ?string
    {
        $ranking = KeywordRanking::where('domain_id', $domainId)
            ->orderBy('snapshot_date', 'desc')
            ->first();

        return $ranking ? $ranking->snapshot_date->format('Y-m-d') : null;
    }

    /**
     * Calculate position change between two snapshots
     *
     * @param int $keywordId
     * @param int $domainId
     * @param string $date1
     * @param string $date2
     * @return int|null Positive means improved, negative means declined
     */
    public function calculatePositionChange(int $keywordId, int $domainId, string $date1, string $date2): ?int
    {
        $ranking1 = KeywordRanking::where('keyword_id', $keywordId)
            ->where('domain_id', $domainId)
            ->where('snapshot_date', $date1)
            ->first();

        $ranking2 = KeywordRanking::where('keyword_id', $keywordId)
            ->where('domain_id', $domainId)
            ->where('snapshot_date', $date2)
            ->first();

        if (!$ranking1 || !$ranking2) {
            return null;
        }

        // Posiciones menores son mejor (1 es mejor que 10)
        // Por lo tanto, cambio positivo = ranking1 - ranking2
        return $ranking1->position - $ranking2->position;
    }

    /**
     * Batch import rankings
     *
     * @param array $rankings
     * @return int Count of imported rankings
     */
    public function batchImport(array $rankings): int
    {
        $count = 0;

        DB::transaction(function () use ($rankings, &$count) {
            foreach ($rankings as $rankingData) {
                $this->importRanking($rankingData);
                $count++;
            }
        });

        return $count;
    }
}
