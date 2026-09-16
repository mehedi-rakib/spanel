<?php

namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\ProductSeoRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use Generator;
use Rap2hpoutre\FastExcel\FastExcel;

class ProductBulkEditService
{
    private const PRODUCT_MODEL = 'App\Models\Product';

    private const IDENTIFIER_COLUMNS = ['id', 'code', 'name'];

    private const INTEGER_FIELDS = [
        'category_id', 'sub_category_id', 'sub_sub_category_id', 'brand_id',
        'minimum_order_qty', 'current_stock', 'status',
    ];

    private const DECIMAL_FIELDS = ['unit_price', 'purchase_price', 'tax', 'discount'];

    private const SIMPLE_FIELDS = [
        'code' => 'product_SKU',
        'category_id' => 'category_id',
        'sub_category_id' => 'sub_category_id',
        'sub_sub_category_id' => 'sub_sub_category_id',
        'brand_id' => 'brand_id',
        'unit' => 'unit',
        'minimum_order_qty' => 'minimum_order_qty',
        'unit_price' => 'unit_price',
        'purchase_price' => 'purchase_price',
        'tax' => 'tax',
        'discount' => 'discount',
        'discount_type' => 'discount_type',
        'current_stock' => 'current_stock',
        'status' => 'status',
    ];

    // field => translations-table key used for non-default languages
    private const TRANSLATABLE_FIELDS = [
        'details' => ['label' => 'description', 'translation_key' => 'description'],
        'meta_title' => ['label' => 'meta_Title', 'translation_key' => 'meta_title'],
        'meta_description' => ['label' => 'meta_Description', 'translation_key' => 'meta_description'],
    ];

    // rows are written to the sheet, and DB writes on upload, in batches of this size
    private const CHUNK_SIZE = 200;

    public function __construct(
        private readonly ProductRepositoryInterface     $productRepo,
        private readonly ProductSeoRepositoryInterface  $productSeoRepo,
        private readonly TranslationRepositoryInterface $translationRepo,
        private readonly StockHistoryService            $stockHistoryService,
    )
    {
    }

    public function languages(): array
    {
        return getWebConfig(name: 'pnc_language') ?: ['en'];
    }

    public function defaultLanguage(): string
    {
        return $this->languages()[0] ?? 'en';
    }

    public function editableFields(): array
    {
        return self::SIMPLE_FIELDS;
    }

    public function translatableFields(): array
    {
        return self::TRANSLATABLE_FIELDS;
    }

    private function columnMap(): array
    {
        $map = [];
        foreach (self::SIMPLE_FIELDS as $field => $label) {
            $map[$field] = ['type' => 'simple', 'field' => $field];
        }
        foreach (self::TRANSLATABLE_FIELDS as $field => $meta) {
            foreach ($this->languages() as $lang) {
                $map[$field . '_' . $lang] = [
                    'type' => 'translatable',
                    'field' => $field,
                    'lang' => $lang,
                    'translation_key' => $meta['translation_key'],
                ];
            }
        }
        return $map;
    }

    /**
     * Streams export rows one product at a time (DB cursor -> generator -> FastExcel),
     * so downloading never has to hold the full product list in memory.
     */
    public function getExportData(array $fields, array $filters = []): Generator
    {
        $columnMap = $this->columnMap();
        $fields = array_values(array_intersect($fields, array_keys($columnMap)));
        $defaultLanguage = $this->defaultLanguage();

        $listFilters = [];
        foreach (['category_id', 'brand_id', 'status'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== 'all') {
                $listFilters[$key] = $filters[$key];
            }
        }
        if (isset($filters['searchValue']) && $filters['searchValue'] !== '') {
            $listFilters['searchValue'] = $filters['searchValue'];
            $listFilters['code'] = $filters['searchValue'];
        }

        $products = $this->productRepo->cursorWhere(filters: $listFilters, relations: ['seoInfo', 'translations']);

        foreach ($products as $product) {
            yield $this->mapProductToRow(product: $product, fields: $fields, columnMap: $columnMap, defaultLanguage: $defaultLanguage);
        }
    }

    private function mapProductToRow(object $product, array $fields, array $columnMap, string $defaultLanguage): array
    {
        $row = [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
        ];

        $translations = [];
        foreach ($product->translations as $translation) {
            $translations[$translation->locale][$translation->key] = $translation->value;
        }

        foreach ($fields as $column) {
            $meta = $columnMap[$column];

            if ($meta['type'] === 'simple') {
                $row[$column] = $product->{$meta['field']};
                continue;
            }

            if ($meta['lang'] === $defaultLanguage) {
                $row[$column] = $this->getDefaultLanguageValue(product: $product, field: $meta['field']);
            } else {
                $row[$column] = $translations[$meta['lang']][$meta['translation_key']] ?? '';
            }
        }

        return $row;
    }

    private function getDefaultLanguageValue(object $product, string $field): ?string
    {
        return match ($field) {
            'details' => $product->details,
            'meta_title' => $product->seoInfo?->title ?? $product->meta_title,
            'meta_description' => $product->seoInfo?->description ?? $product->meta_description,
            default => null,
        };
    }

    /**
     * Reads the uploaded sheet row by row and flushes DB updates every CHUNK_SIZE rows,
     * instead of buffering every row (and every update) in memory before writing anything.
     */
    public function processBulkEditUpload(object $request): array
    {
        $columnMap = $this->columnMap();
        $defaultLanguage = $this->defaultLanguage();

        $headers = null;
        $buffer = [];
        $rowCount = 0;
        $updatedCount = 0;
        $skippedIds = [];
        $errorMessage = null;

        try {
            (new FastExcel)->import($request->file('products_file'), function ($row) use (
                &$headers, &$buffer, &$rowCount, &$updatedCount, &$skippedIds, &$errorMessage,
                $columnMap, $defaultLanguage
            ) {
                if ($errorMessage !== null) {
                    return null;
                }

                if ($headers === null) {
                    $headers = array_keys($row);

                    if (!in_array('id', $headers, true)) {
                        $errorMessage = translate('the_id_column_is_missing_please_do_not_edit_the_downloaded_template_columns');
                        return null;
                    }

                    foreach ($headers as $header) {
                        if (!in_array($header, self::IDENTIFIER_COLUMNS, true) && !array_key_exists($header, $columnMap)) {
                            $errorMessage = translate('Please_upload_the_correct_format_file');
                            return null;
                        }
                    }
                }

                $rowCount++;
                $update = $this->buildUpdateFromRow(row: $row, columnMap: $columnMap, defaultLanguage: $defaultLanguage);

                if ($update === null) {
                    if (!empty($row['id'])) {
                        $skippedIds[] = $row['id'];
                    }
                    return null;
                }

                $buffer[] = $update;
                if (count($buffer) >= self::CHUNK_SIZE) {
                    $updatedCount += $this->applyUpdates($buffer);
                    $buffer = [];
                }

                // returning null keeps FastExcel from accumulating rows in its own collection
                return null;
            });
        } catch (\Exception $exception) {
            return [
                'status' => false,
                'message' => translate('you_have_uploaded_a_wrong_format_file') . ',' . translate('please_upload_the_right_file'),
            ];
        }

        if ($errorMessage !== null) {
            return [
                'status' => false,
                'message' => $errorMessage,
            ];
        }

        if (count($buffer) > 0) {
            $updatedCount += $this->applyUpdates($buffer);
        }

        if ($rowCount <= 0) {
            return [
                'status' => false,
                'message' => translate('you_need_to_upload_with_proper_data'),
            ];
        }

        if ($updatedCount <= 0) {
            return [
                'status' => false,
                'message' => translate('no_valid_product_data_found_to_update'),
            ];
        }

        $message = $updatedCount . ' - ' . translate('products_updated_successfully');
        if (count($skippedIds) > 0) {
            $message .= ', ' . count($skippedIds) . ' ' . translate('rows_skipped_for_invalid_product_id');
        }

        return [
            'status' => true,
            'message' => $message,
        ];
    }

    private function buildUpdateFromRow(array $row, array $columnMap, string $defaultLanguage): ?array
    {
        $id = $row['id'] ?? null;
        if ($id === null || $id === '' || !is_numeric($id) || !$this->productRepo->getFirstWhere(params: ['id' => $id])) {
            return null;
        }

        $data = [];
        $seo = [];
        $translations = [];

        foreach ($row as $column => $value) {
            if (in_array($column, self::IDENTIFIER_COLUMNS, true)) {
                continue;
            }
            if ($value === null || $value === '' || !array_key_exists($column, $columnMap)) {
                continue;
            }

            $meta = $columnMap[$column];

            if ($meta['type'] === 'simple') {
                $data[$meta['field']] = $this->castFieldValue($meta['field'], $value);
                continue;
            }

            if ($meta['lang'] === $defaultLanguage) {
                if ($meta['field'] === 'details') {
                    $data['details'] = (string)$value;
                } else {
                    $seo[$meta['field'] === 'meta_title' ? 'title' : 'description'] = (string)$value;
                }
            } else {
                $translations[] = [
                    'lang' => $meta['lang'],
                    'key' => $meta['translation_key'],
                    'value' => (string)$value,
                ];
            }
        }

        if (count($data) <= 0 && count($seo) <= 0 && count($translations) <= 0) {
            return null;
        }

        return [
            'id' => (int)$id,
            'data' => $data,
            'seo' => $seo,
            'translations' => $translations,
        ];
    }

    private function applyUpdates(array $updates): int
    {
        $updatedCount = 0;
        foreach ($updates as $update) {
            $changed = false;
            $data = $update['data'];

            if (array_key_exists('current_stock', $data)) {
                $this->stockHistoryService->recordAbsolute(
                    productId: $update['id'],
                    type: StockHistoryService::TYPE_BULK_EDIT,
                    newStockValue: (int)$data['current_stock'],
                    note: translate('bulk_edit_sheet_upload')
                );
                unset($data['current_stock']);
                $changed = true;
            }

            if (count($data) > 0 && $this->productRepo->update(id: $update['id'], data: $data)) {
                $changed = true;
            }

            if (count($update['seo']) > 0) {
                $this->productSeoRepo->updateOrInsert(params: ['product_id' => $update['id']], data: $update['seo']);
                $changed = true;
            }

            foreach ($update['translations'] as $translation) {
                $this->translationRepo->updateData(
                    model: self::PRODUCT_MODEL,
                    id: (string)$update['id'],
                    lang: $translation['lang'],
                    key: $translation['key'],
                    value: $translation['value']
                );
                $changed = true;
            }

            if ($changed) {
                $updatedCount++;
            }
        }
        return $updatedCount;
    }

    private function castFieldValue(string $field, mixed $value): int|float|string
    {
        if (in_array($field, self::INTEGER_FIELDS, true)) {
            return (int)$value;
        }

        if (in_array($field, self::DECIMAL_FIELDS, true)) {
            return (float)$value;
        }

        return (string)$value;
    }
}
