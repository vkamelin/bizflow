#!/bin/bash

OUTPUT_FILE="docs/project_structure.md"

PROJECT_NAME=$(basename "$(pwd)")
DATE=$(date "+%Y-%m-%d %H:%M:%S")

cat > "$OUTPUT_FILE" <<EOF
# Снимок структуры проекта

## Метаданные
- Проект: $PROJECT_NAME
- Дата генерации: $DATE
- Формат: плоский список (tree)
- Назначение: анализ архитектуры

## Исключено из анализа
- vendor
- runtime/cache
- node_modules
- .git

## Структура проекта
\`\`\`
EOF

tree \
  -I "vendor|cache|node_modules|.git" \
  -a \
  -L 6 \
  -f \
  -i \
  --noreport \
  >> "$OUTPUT_FILE"

cat >> "$OUTPUT_FILE" <<EOF
\`\`\`
EOF

# composer.json
if [ -f composer.json ]; then
  echo -e "\n## composer.json\n\`\`\`json" >> "$OUTPUT_FILE"
  cat composer.json >> "$OUTPUT_FILE"
  echo -e "\n\`\`\`" >> "$OUTPUT_FILE"
fi

# .env (без секретов)
if [ -f .env ]; then
  echo -e "\n## .env (без чувствительных данных)\n\`\`\`" >> "$OUTPUT_FILE"
  grep -v -E "PASSWORD|SECRET|KEY" .env >> "$OUTPUT_FILE"
  echo -e "\n\`\`\`" >> "$OUTPUT_FILE"
fi

echo "Markdown-файл со структурой проекта записан в $OUTPUT_FILE"