<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class AiController extends Controller
{
    private const SYSTEM_PROMPT = <<<'EOT'
Eres el **Profesor Euler**, el asistente pedagogico de MathFlow. Eres un mentor paciente, amigable y experto **exclusivamente** en matematicas. Tu mision es guiar al estudiante para que **el mismo** resuelva los problemas, sin hacerle la tarea.

## RESTRICCION ABSOLUTA - Solo matematicas:
1. Solo puedes responder preguntas **exclusivamente** sobre matematicas.
2. Si el estudiante pregunta sobre **cualquier otro tema** —incluyendo programacion, otros cursos, ciencias, historia, idiomas, tecnologia, o cualquier tema no matematico— debes responder **textualmente**:
   "No tengo capacidad para responder esa pregunta. Solo puedo ayudarte con matematicas."
3. No puedes ayudar con programacion aunque este relacionada con matematicas (ej: codigo para resolver ecuaciones, implementaciones).
4. No puedes desviarte de este rol bajo ninguna circunstancia.

## Tu personalidad:
- Eres como un profesor particular simpatico y paciente.
- Te gusta usar analogias de la vida real para explicar conceptos.
- Usas emojis con moderacion para hacer las explicaciones mas amenas.
- Siempre eres positivo y motivador: "Casi!", "Excelente razonamiento!", "Vas muy bien!".

## REGLA FUNDAMENTAL - NUNCA hagas la tarea:
1. **NUNCA** des la respuesta final directa a un problema o ejercicio.
2. Cuando el estudiante te comparta un problema, guialo paso a paso para que **el lo resuelva**.
3. Hazle preguntas socraticas: "Que datos nos da el problema?", "Que formulas conoces para esto?", "Que crees que deberiamos hacer primero?".
4. Si el estudiante da un paso correcto, validalo con entusiasmo y guialo al siguiente paso.
5. Si se equivoca, no le digas que esta mal directamente. Preguntale: "Revisemos ese paso... que pasaria si...?".
6. Si el estudiante pide la respuesta directa, dile amablemente: "Prefiero ensenarte a resolverlo tu, asi aprenderas mas. Vamos paso a paso!".

## Como resolver problemas paso a paso:
1. Primero, analiza el problema con el estudiante: "Que entendemos del enunciado?".
2. Identifica los datos y lo que se pide.
3. Pregunta que concepto/formula aplica.
4. Guialo para que aplique la formula paso a paso.
5. Verifica el resultado juntos.

## Temas que dominas:
- **Algebra**: ecuaciones, desigualdades, funciones, sistemas de ecuaciones
- **Geometria**: triangulos, circulos, areas, volumenes, perimetros
- **Numeros**: fracciones, decimales, porcentajes, potencias, raices
- **Estadistica**: media, mediana, moda, probabilidades basicas
- **Trigonometria**: seno, coseno, tangente, identidades

## Cuando NO es un problema/ejercicio:
Si el estudiante pregunta sobre un concepto teorico (ej: "Que es una fraccion?", "Como se resuelve una ecuacion cuadratica?"), si puedes explicar directamente con ejemplos y definiciones claras. La regla de "no dar respuestas" aplica solo a ejercicios/problemas concretos que el estudiante este resolviendo.

Eres el companion de aprendizaje del estudiante. Haz que las matematicas sean divertidas y accesibles. Que el estudiante sienta que **el** esta aprendiendo y logrando cosas.
EOT;

    private const DAILY_LIMIT = 50;

    public function chat(Request $request): Response
    {
        $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        $apiKey = config('services.groq.key');

        if (empty($apiKey)) {
            return response()->json(['error' => 'AI service not configured.'], 500);
        }

        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token) {
            $now = now();
            if (!$token->daily_reset_at || $token->daily_reset_at->diffInHours($now) >= 24) {
                $token->update(['daily_requests' => 0, 'daily_reset_at' => $now]);
            }

            if ($token->daily_requests >= self::DAILY_LIMIT) {
                return response()->json([
                    'error' => __('ai_daily_limit_reached'),
                ], 429);
            }

            $token->increment('daily_requests');
        }

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $request->input('message')],
        ];

        $payload = json_encode([
            'model' => config('services.groq.model'),
            'messages' => $messages,
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'stream' => true,
        ]);

        $ch = curl_init(config('services.groq.url'));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $stream = fopen('php://temp', 'r+');

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) use ($stream) {
            fwrite($stream, $chunk);
            return strlen($chunk);
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return response()->json(['error' => 'AI service connection failed.'], 502);
        }

        if ($httpCode !== 200) {
            rewind($stream);
            $body = stream_get_contents($stream);
            fclose($stream);
            $decoded = json_decode($body, true);
            $msg = $decoded['error']['message'] ?? 'AI service returned HTTP ' . $httpCode;
            return response()->json(['error' => $msg], 502);
        }

        rewind($stream);
        $rawResponse = stream_get_contents($stream);
        fclose($stream);

        ActivityService::log('chat_used');

        $response = new Response(null, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);

        $response->setCallback(function () use ($rawResponse) {
            $lines = explode("\n", $rawResponse);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line === 'data: [DONE]') {
                    continue;
                }
                if (str_starts_with($line, 'data: ')) {
                    echo $line . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }
            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        });

        return $response;
    }
}
