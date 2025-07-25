<?php
/**
 * AI HTTP Client - Unified Streaming Normalizer
 * 
 * Single Responsibility: Handle streaming differences across providers
 * Normalizes streaming requests and processes streaming responses
 *
 * @package AIHttpClient\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Unified_Streaming_Normalizer {

    /**
     * Normalize streaming request for any provider
     *
     * @param array $standard_request Standard streaming request
     * @param string $provider_name Target provider
     * @return array Provider-specific streaming request
     */
    public function normalize_streaming_request($standard_request, $provider_name) {
        switch (strtolower($provider_name)) {
            case 'openai':
                return $this->normalize_openai_streaming_request($standard_request);
            
            case 'anthropic':
                return $this->normalize_anthropic_streaming_request($standard_request);
            
            case 'gemini':
                return $this->normalize_gemini_streaming_request($standard_request);
            
            case 'grok':
                return $this->normalize_grok_streaming_request($standard_request);
            
            case 'openrouter':
                return $this->normalize_openrouter_streaming_request($standard_request);
            
            default:
                throw new Exception("Streaming not supported for provider: {$provider_name}");
        }
    }

    /**
     * Process streaming chunk from any provider
     *
     * @param string $chunk Raw streaming chunk
     * @param string $provider_name Source provider
     * @return array|null Processed chunk data or null if not processable
     */
    public function process_streaming_chunk($chunk, $provider_name) {
        switch (strtolower($provider_name)) {
            case 'openai':
                return $this->process_openai_chunk($chunk);
            
            case 'anthropic':
                return $this->process_anthropic_chunk($chunk);
            
            case 'gemini':
                return $this->process_gemini_chunk($chunk);
            
            case 'grok':
                return $this->process_grok_chunk($chunk);
            
            case 'openrouter':
                return $this->process_openrouter_chunk($chunk);
            
            default:
                return null;
        }
    }

    /**
     * OpenAI streaming request normalization
     */
    private function normalize_openai_streaming_request($request) {
        // Add stream parameter
        $request['stream'] = true;
        return $request;
    }

    /**
     * Anthropic streaming request normalization
     */
    private function normalize_anthropic_streaming_request($request) {
        // Add stream parameter
        $request['stream'] = true;
        return $request;
    }

    /**
     * Gemini streaming request normalization
     */
    private function normalize_gemini_streaming_request($request) {
        // Gemini uses different endpoint for streaming
        // Add any Gemini-specific streaming parameters
        return $request;
    }

    /**
     * Grok streaming request normalization
     */
    private function normalize_grok_streaming_request($request) {
        // Grok uses OpenAI-compatible streaming
        $request['stream'] = true;
        return $request;
    }

    /**
     * OpenRouter streaming request normalization
     */
    private function normalize_openrouter_streaming_request($request) {
        // OpenRouter uses OpenAI-compatible streaming
        $request['stream'] = true;
        return $request;
    }

    /**
     * Process OpenAI streaming chunk
     */
    private function process_openai_chunk($chunk) {
        $lines = explode("\n", $chunk);
        $content = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    break;
                }
                
                $json = json_decode($data, true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    $content .= $json['choices'][0]['delta']['content'];
                }
            }
        }
        
        return array(
            'content' => $content,
            'done' => strpos($chunk, '[DONE]') !== false
        );
    }

    /**
     * Process Anthropic streaming chunk
     */
    private function process_anthropic_chunk($chunk) {
        $lines = explode("\n", $chunk);
        $content = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                
                $json = json_decode($data, true);
                if ($json && isset($json['delta']['text'])) {
                    $content .= $json['delta']['text'];
                }
            }
        }
        
        return array(
            'content' => $content,
            'done' => strpos($chunk, 'event: message_stop') !== false
        );
    }

    /**
     * Process Gemini streaming chunk
     */
    private function process_gemini_chunk($chunk) {
        $json = json_decode($chunk, true);
        $content = '';
        
        if ($json && isset($json['candidates'][0]['content']['parts'])) {
            foreach ($json['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $content .= $part['text'];
                }
            }
        }
        
        return array(
            'content' => $content,
            'done' => isset($json['candidates'][0]['finishReason'])
        );
    }

    /**
     * Process Grok streaming chunk (OpenAI-compatible)
     */
    private function process_grok_chunk($chunk) {
        return $this->process_openai_chunk($chunk);
    }

    /**
     * Process OpenRouter streaming chunk (OpenAI-compatible)
     */
    private function process_openrouter_chunk($chunk) {
        return $this->process_openai_chunk($chunk);
    }
}