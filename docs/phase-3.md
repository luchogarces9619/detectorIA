# Fase 3 — Detector calibrado y versionado

**Estado:** implementación completa; métricas definitivas pendientes de la ejecución del corpus completo.

## 1. Objetivo

Entrenar, calibrar, evaluar y versionar un detector real de escritura generada por IA en resúmenes académicos en español, sin APIs comerciales, sin usar documentos de usuarios y sin presentar el resultado como prueba de autoría.

La fase entrega cuatro cosas verificables:

1. Un corpus propio y auditable, con procedencia y licencia por documento.
2. Un artefacto de modelo reproducible, con métricas y umbrales de abstención.
3. Un endpoint interno que puntúa fragmentos y devuelve `not_conclusive` cuando no hay evidencia suficiente.
4. Un registro de versiones en Laravel que verifica el SHA-256 del artefacto antes de guardarlo.

## 2. Alcance

### Incluido

- Corpus humano de resúmenes publicados en revistas de acceso abierto indexadas en DOAJ.
- Corpus sintético generado localmente con Qwen2.5-1.5B-Instruct (GGUF Q4_K_M) mediante `llama-server`.
- Normalización común para entrenamiento e inferencia.
- Particiones deterministas por revista: `train`, `calib`, `threshold` y `test`.
- Modelo TF-IDF (palabras y caracteres) + regresión logística.
- Calibración isotónica y umbrales de abstención con precisión objetivo.
- Evaluación por fragmento y por documento, incluida una prueba con un generador no visto (OOD).
- Artefacto versionado con `card.json`, `metrics.json`, `dataset.json` y verificación SHA-256.
- Endpoint `POST /internal/v1/detect` y comando `scitrace:models:register`.

### No incluido

- Orquestación del análisis desde la interfaz web (Fase 4).
- Explicaciones por fragmento e indicadores visuales (Fase 4).
- Informes PDF (Fase 5).
- Entrenamiento de transformers grandes: el equipo no tiene GPU NVIDIA.
- Detección de autoría. El sistema solo estima señales de estilo.

## 3. Decisiones de diseño

| Decisión | Motivo | Alternativa descartada |
| --- | --- | --- |
| Corpus propio | Permite auditar licencia, idioma y procedencia | Benchmarks sin licencia o inaccesibles |
| Generación local con `llama.cpp` | Sin APIs comerciales, reproducible y sin enviar datos a terceros | Servicios de pago |
| TF-IDF + regresión logística | Entrena y predice en CPU, es pequeño y explicable | Fine-tuning de transformers sin GPU |
| Partición de prueba por revista | Mide generalización a revistas y temas no vistos | Partición aleatoria con fuga temática |
| Calibración isotónica | Convierte puntuaciones en probabilidades utilizables | Umbral fijo en 0,5 |
| Umbrales con abstención | Permite un `not_conclusive` real y medible | Forzar siempre una etiqueta |
| Normalización compartida | Evita que el modelo aprenda formato de generación | Normalizar solo el corpus |
| Verificación SHA-256 | Impide cargar un artefacto alterado o incompleto | Confiar solo en la ruta |

## 4. Corpus

### 4.1 Textos humanos

- Fuente: API pública de DOAJ (`https://doaj.org/api`).
- Tipo: resúmenes de artículos académicos.
- Idioma: español, verificado con una heurística de marcas y con `langid`.
- Licencias admitidas: CC BY, CC BY-SA, CC BY-NC, CC BY-NC-SA y CC0, declaradas por la revista.
- Filtros: entre 600 y 2600 caracteres, al menos 90 palabras, sin duplicados por texto ni por DOI.
- Tamaño: 1000 resúmenes de 38 revistas.
- Corpus limpio: 862 resúmenes tras excluir 138 (13,8 %) con etiquetas de sección, Markdown o título como encabezado. La misma exclusión se aplica al corpus sintético para que la etiqueta no se convierta en una señal espuria de autoría humana.
- Procedencia por registro: DOI, revista, ISSN, licencia, URL, año y SHA-256 del texto.

Los resúmenes se publican con licencia de reutilización; no se usaron documentos privados ni manuscritos de usuarios. El corpus se excluye del repositorio (`.gitignore`) y se regenera con los comandos de la sección 9.

### 4.2 Textos sintéticos

- Generador: `Qwen/Qwen2.5-1.5B-Instruct-GGUF`, cuantización Q4_K_M, licencia Apache-2.0.
- Runtime: `llama.cpp` b11840, servidor local en `127.0.0.1`.
- Mensaje de sistema: define el rol de investigador, exige prosa continua y prohíbe Markdown, viñetas, encabezados, etiquetas de sección y la mención del título.
- Prompt principal (666 registros con palabras clave): pide un resumen de 180–240 palabras sobre el tema descrito por las palabras clave, en un solo párrafo. No incluye el título, de modo que el modelo no tiene un encabezado que copiar.
- Prompt de respaldo (196 registros sin palabras clave): usa el título como única fuente de tema y exige no repetirlo ni citarlo.
- Parámetros: temperatura 0,8, `top_p` 0,95, máximo 380 tokens nuevos.
- Semilla: `20261023 + índice global del registro humano`, de modo que el orden de finalización no altera el texto de cada par.
- Concurrencia: 4 peticiones simultáneas, ajustadas a las 4 ranuras del servidor.
- Verificación: el generador consulta `/props` y aborta si el modelo servido no coincide con `--expect-model`.
- Calidad: si el modelo alcanza el límite de tokens, el texto se recorta en la última oración completa. Nunca se guarda una palabra cortada. Los textos resultantes deben tener entre 90 y 300 palabras; el mínimo coincide con el mínimo del corpus humano.
- Filtro de artefactos: antes de escribir un registro se descarta si contiene Markdown (`**`, `##`), etiquetas de sección («Objetivo:», «Metodología:», «Resultados:», «Conclusión:»), fórmulas de anuncio («es el siguiente», «a continuación se presenta») o el título como encabezado. El descarte se informa en la salida y no se escribe al corpus. `training.filter_human` aplica el mismo criterio al corpus humano.
- Longitud: el generador produce en torno a 145–150 palabras de media, algo menos que el corpus humano (175). El entrenamiento puntúa fragmentos de 45–130 palabras, así que la diferencia no se usa como señal directa; se documenta como limitación.
- El límite se amplió de 320 a 380 tokens al detectar truncamientos. Los registros anteriores se recortaron con la misma regla y conservan su parámetro original en `generator.max_new_tokens`; el campo permite auditar la diferencia.
- Registro: cada texto guarda el prompt, su tipo (`prompt_kind`), el mensaje de sistema, el modelo, la ruta servida, la cuantización, los parámetros y la semilla.

La primera generación se descartó íntegramente: el prompt anterior pedía «objetivo, metodología, resultados y conclusión», y el modelo respondió con etiquetas de sección (43 %), Markdown (40 %) y el título repetido (35 %). Entrenar con ese corpus habría enseñado al detector a reconocer el formato del generador. El corpus descartado no forma parte del artefacto ni de las métricas.

Los textos sintéticos no se presentan como textos humanos: son datos de entrenamiento, no una simulación de un usuario real.

### 4.3 Esquema de registro

```json
{
  "id": "ai-human-00042--qwen--qwen2.5-1.5b-instruct-gguf:q4_k_m",
  "pair_id": "human-00042",
  "text": "…",
  "label": "ai",
  "language": "es",
  "title": "…",
  "keywords": ["…"],
  "source": {
    "kind": "local-generation",
    "prompt": "…",
    "prompt_kind": "keywords",
    "system_prompt": "…",
    "derived_from": "human-00042",
    "derived_from_license": "CC BY"
  },
  "generator": {
    "model": "Qwen/Qwen2.5-1.5B-Instruct-GGUF:q4_k_m",
    "runtime": "llama.cpp",
    "quantization": "q4_k_m",
    "temperature": 0.8,
    "top_p": 0.95,
    "seed": 20261065
  },
  "group": "0124-2253"
}
```

### 4.4 Auditoría

`python -m training.audit` comprueba, antes de entrenar:

- idioma de ambos corpus con `langid`;
- textos sintéticos huérfanos o pares duplicados;
- textos normalizados repetidos;
- fuga entre el texto humano y su contraparte sintética mediante `containment` de fragmentos de 13 palabras;
- artefactos del generador (Markdown, etiquetas de sección, fórmulas de anuncio y título) como problema bloqueante;
- tamaño y cobertura de la partición de prueba;
- distribución de licencias, generadores y longitudes.

El informe se guarda en `docs/phase-3-corpus-audit.json`. Un problema bloqueante devuelve código de salida distinto de cero.

## 5. Particiones

La partición se calcula con SHA-256 estable, no con `random`:

1. El 20 % de las revistas se reserva para `test`. Un artículo pertenece a `test` si su revista fue seleccionada.
2. El resto se reparte entre `train` (70 %), `calib` (15 %) y `threshold` (15 %) por identificador de par.
3. La contraparte sintética hereda la partición del artículo humano.

Con el corpus actual:

| Partición | Pares humanos | Revistas | Uso |
| --- | ---: | ---: | --- |
| `train` | 504 | 30 | Ajuste del modelo |
| `calib` | 130 | — | Ajuste de la calibración isotónica |
| `threshold` | 118 | — | Selección de umbrales |
| `test` | 248 | 8 | Evaluación final, nunca usada para ajustar |

`test` se evalúa una sola vez al final. Las métricas de `calib` y `threshold` son de ajuste y no deben presentarse como generalización.

## 6. Modelo y calibración

### 6.1 Representación

- TF-IDF de palabras con n-gramas de 1–2, `min_df=2`, `max_df=0.9`, `sublinear_tf`, hasta 80 000 características.
- TF-IDF de caracteres `char_wb` con n-gramas de 3–5, `min_df=3`, hasta 120 000 características.
- Ambas representaciones se combinan con `FeatureUnion`.

### 6.2 Clasificador

- Regresión logística `liblinear`, `class_weight="balanced"`, `max_iter=3000`.
- El parámetro `C` se elige entre `0,5`, `1`, `2`, `4`, `8`, `16` y `32` por log-loss medio en validación cruzada agrupada por revista (`GroupKFold`, 3 pliegues). Agrupar por revista evita que el mismo tema aparezca en entrenamiento y validación.

### 6.3 Calibración

- Se ajusta `IsotonicRegression` sobre la partición `calib`, con límites `[0, 1]`.
- La calibración es monótona y no cambia el orden de las puntuaciones, pero sí su interpretación probabilística.
- Se reportan `roc_auc`, `pr_auc`, `brier`, `log-loss`, `ECE` y la tabla de fiabilidad por deciles.

### 6.4 Umbrales y abstención

- Se eligen sobre la partición `threshold`, con precisión objetivo 0,90.
- `high`: puntuación mínima para etiquetar `ai` con la precisión objetivo.
- `low`: puntuación máxima para etiquetar `human` con la precisión objetivo.
- Si la probabilidad cae entre ambos, la etiqueta es `uncertain` y `requires_review=true`.
- Los empates de la calibración isotónica se evalúan como grupo completo, de modo que la precisión reportada coincide con la regla de decisión real.
- Si no se alcanza la precisión objetivo en alguna de las dos colas, `valid=false` y el detector se abstiene siempre. No se usan umbrales de reserva para decidir.

### 6.5 Agregación documental

- Cada fragmento se normaliza y se divide en bloques de 45–130 palabras.
- La puntuación del fragmento es la media de las probabilidades calibradas de sus bloques.
- La puntuación del documento es la media de los fragmentos puntuables.
- `coverage` es la proporción de fragmentos con decisión (no `uncertain`).
- La conclusión es:
  - `review_suggested` si al menos el 60 % de los fragmentos son `ai` y la media supera `high`;
  - `no_signals` si al menos el 80 % son `human` y la media no supera `low`;
  - `mixed_signals` si hay señales de ambas clases;
  - `not_conclusive` en cualquier otro caso, incluida la falta de texto puntuable.

Nunca se calcula un porcentaje de texto generado por IA.

## 7. Artefacto y verificación

El artefacto se escribe en `ai-service/models/scitrace-es-v1/`:

| Archivo | Contenido |
| --- | --- |
| `model.joblib` | Pipeline TF-IDF + regresión logística, calibrador, umbrales y metadatos |
| `card.json` | Nombre, versión, tarea, SHA-256, configuración, métricas, entorno y limitaciones |
| `metrics.json` | Métricas completas por partición y por nivel |
| `dataset.json` | Tamaño, particiones, licencias, generadores y SHA-256 de los archivos de corpus |

Al cargar:

1. Se comprueba que existen `model.joblib` y `card.json`.
2. Se recalcula el SHA-256 del artefacto y se compara con la tarjeta.
3. Solo entonces se deserializa con `joblib` y se marca el detector como disponible.
4. La instancia se cachea y se recarga si cambia la fecha de modificación del artefacto.

Si algo falla, `/health` devuelve `detector_ready=false`, `active_model=null` y `/internal/v1/detect` responde `503`. No se devuelven puntuaciones inventadas.

## 8. API interna

### `POST /internal/v1/detect`

Requiere `X-Internal-Token`. Acepta entre 1 y 500 fragmentos, 20 000 caracteres por fragmento y 400 000 en total.

Respuesta:

```json
{
  "model": {
    "name": "scitrace-es-academic-detector",
    "version": "1.0.0",
    "artifact_sha256": "…",
    "high_threshold": 0.0,
    "low_threshold": 0.0
  },
  "document": {
    "aggregate_score": 0.0,
    "conclusion": "not_conclusive",
    "confidence": "insufficient",
    "coverage": 0.0
  },
  "segments": [
    {
      "index": 0,
      "score": 0.5,
      "label": "uncertain",
      "confidence": "low",
      "requires_review": true,
      "word_count": 0,
      "chunk_count": 0
    }
  ],
  "disclaimer": "Resultado orientativo para revisión humana. No demuestra autoría ni calcula un porcentaje de texto generado por IA."
}
```

Los valores numéricos del ejemplo son ilustrativos; la respuesta real se obtiene del artefacto cargado.

## 9. Comandos reproducibles

Todos los comandos se ejecutan desde `ai-service/`.

### 9.1 Corpus humano

```powershell
.venv\Scripts\python.exe -m training.dataset `
  --output data/human.jsonl `
  --target 1000

.venv\Scripts\python.exe -m training.filter_human `
  --input data/human.jsonl `
  --output data/human-clean.jsonl `
  --report data/human-clean-report.json
```

El segundo comando excluye los resúmenes con etiquetas de sección, Markdown o título como encabezado. El informe conserva los identificadores y motivos; el archivo original no se modifica.

### 9.2 Corpus sintético

Arrancar el servidor:

```powershell
& "$env:TEMP\scitrace-llama\llama-server.exe" `
  -m "$env:TEMP\scitrace-llama\qwen2.5-1.5b-instruct-q4_k_m.gguf" `
  -c 4096 -t 6 --parallel 4 --host 127.0.0.1 --port 8091 --no-webui
```

Generar:

```powershell
.venv\Scripts\python.exe -m training.generate_llamacpp `
  --input data/human-clean.jsonl `
  --output data/ai.jsonl `
  --concurrency 4 `
  --expect-model 1.5b
```

El generador reanuda: omite los identificadores ya presentes en la salida. Si se interrumpe, se vuelve a ejecutar el mismo comando. Para recuperar los pares descartados por artefactos o longitud se repite con otra semilla (`--seed 20261024`); solo se generan los ausentes y cada registro conserva la semilla que se usó realmente.

### 9.3 Auditoría

```powershell
.venv\Scripts\python.exe -m training.audit `
  --human data/human-clean.jsonl `
  --ai data/ai.jsonl `
  --output ..\docs\phase-3-corpus-audit.json
```

### 9.4 Entrenamiento

```powershell
.venv\Scripts\python.exe -m training.train `
  --human data/human-clean.jsonl `
  --ai data/ai.jsonl `
  --output models/scitrace-es-v1 `
  --version 1.0.0
```

El comando imprime las métricas de `test` y escribe el artefacto. El informe legible se genera desde esos archivos, sin recalcular nada:

```powershell
.venv\Scripts\python.exe -m training.report `
  --model-dir models/scitrace-es-v1 `
  --output ..\docs\phase-3-model-report.md
```

### 9.5 Generalización con otro generador

Arrancar el servidor con Qwen2.5-0.5B en el puerto 8092:

```powershell
& "$env:TEMP\scitrace-llama\llama-server.exe" `
  -m "$env:TEMP\scitrace-llama\qwen2.5-0.5b-instruct-q4_k_m.gguf" `
  -c 4096 -t 6 --parallel 4 --host 127.0.0.1 --port 8092 --no-webui
```

Generar solo la partición de prueba, con el mismo prompt y el mismo filtro de artefactos:

```powershell
.venv\Scripts\python.exe -m training.generate_llamacpp `
  --input data/human-clean.jsonl `
  --output data/ood-ai.jsonl `
  --only-split test `
  --model-label Qwen/Qwen2.5-0.5B-Instruct-GGUF:q4_k_m `
  --expect-model 0.5b `
  --base-url http://127.0.0.1:8092
```

Reevaluar:

```powershell
.venv\Scripts\python.exe -m training.train `
  --human data/human-clean.jsonl `
  --ai data/ai.jsonl `
  --ood-ai data/ood-ai.jsonl `
  --output models/scitrace-es-v1 `
  --version 1.0.0
```

### 9.6 Servicio

```powershell
$env:AI_INTERNAL_TOKEN = "<token de al menos 32 caracteres>"
$env:AI_MODEL_DIR = "models/scitrace-es-v1"
.venv\Scripts\python.exe -m uvicorn app.main:create_app --factory --host 127.0.0.1 --port 8001
```

### 9.7 Registro de la versión en Laravel

```powershell
php artisan scitrace:models:register ..\ai-service\models\scitrace-es-v1\card.json
```

El comando verifica el SHA-256, rechaza reutilizar una versión con otro artefacto y registra el modelo como `candidate`. No lo activa: la activación y la orquestación pertenecen a la Fase 4.

## 10. Variables de entorno

| Variable | Uso | Valor por defecto |
| --- | --- | --- |
| `AI_INTERNAL_TOKEN` | Token compartido con Laravel | Obligatorio, mínimo 32 caracteres |
| `AI_DETECTOR_ENABLED` | Habilita el endpoint de detección | `true` |
| `AI_DETECTOR_READY` | Interruptor de despliegue; no sustituye la verificación del artefacto | `true` |
| `AI_MODEL_DIR` | Directorio del artefacto | `models/scitrace-es-v1` |
| `AI_MAX_DOCUMENT_BYTES` | Límite de extracción | 25 MB |

`AI_DETECTOR_READY=true` no implica que el detector esté operativo: el estado real depende de que el artefacto exista y supere la verificación SHA-256.

## 11. Verificación ejecutada

**Fecha:** 2026-10-25  
**Artefacto:** `models/scitrace-es-v1/model.joblib`  
**SHA-256:** `0b5d15a83b5b7578baf6d217ebd1ab056601a7fbe190a383d58a8b160a622cf5`  
**Versión registrada:** 1.0.0 (`candidate`)

### Corpus
- Humano limpio: 862 resúmenes, 38 revistas, 100 % español (langid), licencias CC
- Sintético: 811 textos (94,1 % cobertura), Qwen2.5-1.5B-Instruct Q4_K_M, filtro de artefactos simétrico
- Pares emparejados: 811; particiones deterministas por revista (test 211 pares, 8 revistas no vistas)

### Métricas de `test` (no usadas para ajustar)

| Nivel | ROC AUC | PR AUC | Exactitud @ 0,5 | F1 @ 0,5 | Cobertura decisión | Exactitud decisión | Precisión decisión | Sensibilidad decisión | F1 decisión |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Fragmentos | 0,9968 | 0,9957 | 0,9714 | 0,9691 | 97,29 % | 0,9660 | 0,9350 | 0,9967 | 0,9649 |
| Documentos | **0,9987** | **0,9987** | 0,9858 | 0,9857 | **97,16 %** | **0,9756** | **0,9543** | **1,0000** | **0,9766** |

### Umbrales calibrados (isotónica, precisión objetivo 0,90)
- `high = 0,25` (precisión 0,9265, recall 0,9767)
- `low = 0,1538` (precisión 0,9793, recall 0,9342)
- `valid = true`

### Generalización OOD (Qwen2.5-0.5B-Instruct Q4_K_M, 140 pares test)
- ROC AUC documentos: 0,9994
- Exactitud decisión documentos: 0,9707
- Cobertura: 97,50 %

### Verificación de endpoint
- `POST /internal/v1/detect` responde 200 con puntuaciones calibradas, conclusión (`review_suggested` / `no_signals` / `mixed_signals` / `not_conclusive`) y aviso legal.
- Prueba en 10 pares `test` no vistos: 10/10 humanos → `human`, 10/10 IA → `ai`.

### Suite de pruebas
- Laravel: **68 pruebas, 238 aserciones** (Pint limpio)
- FastAPI: **56 pasan, 1 omitida** (ruff limpio)
- Entrenamiento: pruebas unitarias de umbrales y decisiones incluidas y pasan.

## 12. Errores comunes

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| `/detect` responde `503` | Falta el artefacto o el SHA-256 no coincide | Revisar `AI_MODEL_DIR` y regenerar `card.json` |
| `/health` muestra `active_model=null` | Artefacto inválido o detector deshabilitado | Revisar el motivo en `/capabilities` |
| El generador aborta con «El servidor no corresponde» | Se arrancó otro modelo | Usar `--expect-model` correcto o reiniciar el servidor |
| El generador va a 0,07 textos/s | Una sola ranura efectiva o competencia entre servidores | Usar un servidor con `--parallel 4` y concurrencia 4 |
| La auditoría falla por fuga | El prompt reprodujo el título o el resumen humano | Regenerar esos pares con el prompt actual |
| `valid=false` en los umbrales | La precisión objetivo no se alcanzó | No usar el modelo para decidir; revisar corpus o calibrar de nuevo |

## 13. Limitaciones y ética

- El detector estima señales de estilo; **no demuestra autoría** ni prueba de uso de IA.
- Se entrenó con resúmenes académicos en español y un generador local. No generaliza automáticamente a otros géneros, idiomas o modelos.
- La evaluación OOD con Qwen2.5-0.5B mide un cambio de generador, no todo el espacio de modelos posibles.
- Los textos humanos son resúmenes publicados; los sintéticos fueron generados localmente. El dominio no incluye tesis completas, ensayos ni exámenes.
- No se entrenó con documentos de usuarios. Cualquier uso futuro de textos privados requeriría autorización explícita y registrada, conforme a la LOPDP de Ecuador.
- El corpus se excluye del repositorio para no redistribuir textos de terceros.
- El sistema debe mostrar siempre el aviso de resultado orientativo y permitir la revisión humana.

## 14. Criterios de aceptación

- [ ] El corpus humano limpio tiene al menos 850 resúmenes en español, con licencia y procedencia.
- [ ] El corpus sintético cubre al menos el 90 % de los pares humanos.
- [ ] La auditoría no reporta idioma incorrecto, duplicados ni fuga por encima del umbral.
- [ ] La partición de prueba tiene al menos 50 pares y 3 revistas.
- [ ] El modelo entrena en CPU y produce un artefacto con `card.json`, `metrics.json` y `dataset.json`.
- [ ] El SHA-256 del artefacto coincide con la tarjeta y el servicio lo carga.
- [ ] `POST /internal/v1/detect` responde con puntuaciones calibradas y `not_conclusive` cuando corresponde.
- [ ] Las métricas de `test` se reportan sin usar esa partición para ajustar nada.
- [ ] La suite de FastAPI y la de Laravel pasan; `ruff` y `Pint` están limpios.
- [ ] La documentación declara limitaciones y no presenta el resultado como prueba de autoría.

## 15. Cierre y transición

La Fase 3 termina cuando la verificación de la sección 11 esté completa y el artefacto registrado. La Fase 4 conectará el detector con la interfaz: orquestación del análisis, almacenamiento de predicciones, explicaciones por fragmento y revisión humana.
