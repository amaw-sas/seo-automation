<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\SerpFeaturesParser;
use PHPUnit\Framework\TestCase;

class SerpFeaturesParserTest extends TestCase
{
    /**
     * Test parsing standard CSV string
     */
    public function test_parse_standard_csv_string(): void
    {
        $result = SerpFeaturesParser::parse('Featured Snippet, People Also Ask');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(['Featured Snippet', 'People Also Ask'], $result);
    }

    /**
     * Test parsing CSV with multiple features
     */
    public function test_parse_multiple_features(): void
    {
        $result = SerpFeaturesParser::parse('Featured Snippet, People Also Ask, Image Pack, Video');

        $this->assertCount(4, $result);
        $this->assertEquals([
            'Featured Snippet',
            'People Also Ask',
            'Image Pack',
            'Video'
        ], $result);
    }

    /**
     * Test parsing single feature
     */
    public function test_parse_single_feature(): void
    {
        $result = SerpFeaturesParser::parse('Featured Snippet');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(['Featured Snippet'], $result);
    }

    /**
     * Test parsing null input
     */
    public function test_parse_null_returns_null(): void
    {
        $result = SerpFeaturesParser::parse(null);

        $this->assertNull($result);
    }

    /**
     * Test parsing empty string
     */
    public function test_parse_empty_string_returns_null(): void
    {
        $result = SerpFeaturesParser::parse('');

        $this->assertNull($result);
    }

    /**
     * Test parsing with extra whitespace
     */
    public function test_parse_with_extra_whitespace(): void
    {
        $result = SerpFeaturesParser::parse('  Featured Snippet  ,  People Also Ask  ,  Image Pack  ');

        $this->assertCount(3, $result);
        $this->assertEquals(['Featured Snippet', 'People Also Ask', 'Image Pack'], $result);
    }

    /**
     * Test parsing with empty values in CSV
     */
    public function test_parse_filters_empty_values(): void
    {
        $result = SerpFeaturesParser::parse('Featured Snippet, , People Also Ask, ');

        $this->assertCount(2, $result);
        $this->assertEquals(['Featured Snippet', 'People Also Ask'], $result);
    }

    /**
     * Test parseToJson method
     */
    public function test_parse_to_json(): void
    {
        $result = SerpFeaturesParser::parseToJson('Featured Snippet, People Also Ask');

        $this->assertIsString($result);
        $this->assertJson($result);
        $this->assertEquals('["Featured Snippet","People Also Ask"]', $result);
    }

    /**
     * Test parseToJson with null input
     */
    public function test_parse_to_json_null_returns_null(): void
    {
        $result = SerpFeaturesParser::parseToJson(null);

        $this->assertNull($result);
    }

    /**
     * Test parseToJson with empty string
     */
    public function test_parse_to_json_empty_returns_null(): void
    {
        $result = SerpFeaturesParser::parseToJson('');

        $this->assertNull($result);
    }

    /**
     * Test hasFeature method
     */
    public function test_has_feature(): void
    {
        $features = ['Featured Snippet', 'People Also Ask', 'Image Pack'];

        $this->assertTrue(SerpFeaturesParser::hasFeature($features, 'Featured Snippet'));
        $this->assertTrue(SerpFeaturesParser::hasFeature($features, 'People Also Ask'));
        $this->assertFalse(SerpFeaturesParser::hasFeature($features, 'Video'));
    }

    /**
     * Test hasFeature with null features
     */
    public function test_has_feature_null_returns_false(): void
    {
        $this->assertFalse(SerpFeaturesParser::hasFeature(null, 'Featured Snippet'));
    }

    /**
     * Test hasFeature with empty array
     */
    public function test_has_feature_empty_array_returns_false(): void
    {
        $this->assertFalse(SerpFeaturesParser::hasFeature([], 'Featured Snippet'));
    }

    /**
     * Test getCommonFeatures method
     */
    public function test_get_common_features(): void
    {
        $features = SerpFeaturesParser::getCommonFeatures();

        $this->assertIsArray($features);
        $this->assertNotEmpty($features);
        $this->assertContains('Featured Snippet', $features);
        $this->assertContains('People Also Ask', $features);
        $this->assertContains('Image Pack', $features);
    }

    /**
     * Test parsing features with commas inside names (edge case)
     */
    public function test_parse_features_with_special_characters(): void
    {
        // This documents current behavior - features with commas would be split
        $result = SerpFeaturesParser::parse('Feature 1, Feature 2, Feature 3');

        $this->assertCount(3, $result);
    }

    /**
     * Test array values are re-indexed after filtering
     */
    public function test_array_values_reindexed(): void
    {
        $result = SerpFeaturesParser::parse('Feature 1, , Feature 2');

        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayNotHasKey(2, $result);
        $this->assertEquals('Feature 1', $result[0]);
        $this->assertEquals('Feature 2', $result[1]);
    }
}
