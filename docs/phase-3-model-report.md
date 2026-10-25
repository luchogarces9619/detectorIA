# Informe del detector: scitrace-es-academic-detector v1.0.0

- Artefacto: `model.joblib`
- SHA-256: `0b5d15a83b5b7578baf6d217ebd1ab056601a7fbe190a383d58a8b160a622cf5`
- Creado: 2026-10-25T00:37:56.264024+00:00
- Umbrales: `high=0.25`, `low=0.1538`, `valid=True`
- Corpus: 811 pares, 38 revistas
- Particiones: {'calib': 107, 'train': 402, 'threshold': 91, 'test': 211}

## Test (no usado para ajustar)

### Fragmentos

| Métrica | Valor |
| --- | ---: |
| ROC AUC | 0.9968 |
| PR AUC | 0.9957 |
| Brier | 0.0205 |
| Log-loss | 0.0733 |
| ECE (10 bins) | 0.0228 |
| Exactitud @ 0,5 | 0.9714 |
| F1 @ 0,5 | 0.9691 |
| Elementos | 665 |
| Tasa de IA | 0.4617 |

### Decisiones por fragmento

| Métrica | Valor |
| --- | ---: |
| Cobertura | 0.9729 |
| Abstención | 0.0271 |
| Decididos | 647 |
| Abstenidos | 18 |
| Exactitud | 0.9660 |
| Precisión | 0.9350 |
| Sensibilidad | 0.9967 |
| F1 | 0.9649 |

### Documentos

| Métrica | Valor |
| --- | ---: |
| ROC AUC | 0.9987 |
| PR AUC | 0.9987 |
| Brier | 0.0141 |
| Log-loss | 0.0582 |
| ECE (10 bins) | 0.0282 |
| Exactitud @ 0,5 | 0.9858 |
| F1 @ 0,5 | 0.9857 |
| Elementos | 422 |
| Tasa de IA | 0.5000 |

### Decisiones por documento

| Métrica | Valor |
| --- | ---: |
| Cobertura | 0.9716 |
| Abstención | 0.0284 |
| Decididos | 410 |
| Abstenidos | 12 |
| Exactitud | 0.9756 |
| Precisión | 0.9543 |
| Sensibilidad | 1.0000 |
| F1 | 0.9766 |

## Generalización con otro generador (OOD)

- Generador: `Qwen/Qwen2.5-0.5B-Instruct-GGUF:q4_k_m`
- Pares: 140

### Documentos OOD

| Métrica | Valor |
| --- | ---: |
| ROC AUC | 0.9994 |
| PR AUC | 0.9993 |
| Brier | 0.0122 |
| Log-loss | 0.0561 |
| ECE (10 bins) | 0.0364 |
| Exactitud @ 0,5 | 0.9893 |
| F1 @ 0,5 | 0.9893 |
| Elementos | 280 |
| Tasa de IA | 0.5000 |

### Decisiones OOD por documento

| Métrica | Valor |
| --- | ---: |
| Cobertura | 0.9750 |
| Abstención | 0.0250 |
| Decididos | 273 |
| Abstenidos | 7 |
| Exactitud | 0.9707 |
| Precisión | 0.9459 |
| Sensibilidad | 1.0000 |
| F1 | 0.9722 |
