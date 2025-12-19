<?php

namespace Artryazanov\ArtisanTranslator\Enums;

/**
 * Supported Gemini/Gemma model identifiers for the translator integration.
 */
enum GeminiModel: string
{
    case GEMINI_3_0_PRO = 'gemini-3.0-pro';
    case GEMINI_3_0_FLASH = 'gemini-3.0-flash';
    case GEMINI_2_5_PRO = 'gemini-2.5-pro';
    case GEMINI_2_5_FLASH = 'gemini-2.5-flash';
    case GEMINI_2_5_FLASH_LITE = 'gemini-2.5-flash-lite';
    case GEMMA_3_27B_IT = 'gemma-3-27b-it';
}
