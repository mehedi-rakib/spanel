<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Utils\A4Paper;
use App\Utils\Helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * PDF / Excel downloads for the mobile app.
 *
 * The app can't attach its bearer token to a plain browser download, so it first
 * calls POST /exports (authenticated) and gets back a short-lived signed link to
 * GET /exports/download, which Android hands to the browser / download manager.
 * The signature is relative (path + query only) so it still validates behind a
 * proxy that changes the scheme or host. Files are built on the fly, never stored.
 */
class ExportController extends Controller
{
    private const TYPES = [
        'invoice', 'purchase-bill', 'sale-report', 'purchase-report', 'item-sales',
        'stock-summary', 'profit-loss', 'daybook', 'due',
    ];

    /** Only these filters are copied into the signed link. */
    private const PARAMS = [
        'id', 'reference', 'from_date', 'to_date', 'date', 'payment_status',
        'customer_id', 'supplier_id', 'searchValue', 'stock',
    ];

    private const ALL_ROWS = 100000;

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:' . implode(',', self::TYPES),
            'format' => 'required|in:pdf,xlsx',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $params = ['type' => $request['type'], 'format' => $request['format']];
        foreach (self::PARAMS as $key) {
            if ($request->filled($key)) {
                $params[$key] = (string)$request[$key];
            }
        }

        $path = URL::temporarySignedRoute('api.v4.admin.exports.download', now()->addMinutes(15), $params, false);

        return response()->json([
            'path' => $path,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ], 200);
    }

    public function download(Request $request)
    {
        $type = $request['type'];
        $format = $request['format'] === 'xlsx' ? 'xlsx' : 'pdf';
        if (!in_array($type, self::TYPES, true)) {
            abort(404);
        }

        $data = match ($type) {
            'invoice' => $this->invoiceData($request),
            'purchase-bill' => $this->purchaseBillData($request),
            'sale-report' => $this->saleReportData($request),
            'purchase-report' => $this->purchaseReportData($request),
            'item-sales' => $this->itemSalesData($request),
            'stock-summary' => $this->stockSummaryData($request),
            'profit-loss' => $this->profitLossData($request),
            'daybook' => $this->dayBookData($request),
            'due' => $this->dueData(),
        };
        if ($data === null) {
            abort(404);
        }

        return $format === 'xlsx' ? $this->xlsxResponse($data) : $this->pdfResponse($data);
    }

    // ------------------------------------------------------------------
    // Datasets. Each returns: title, filename, meta, summary, columns
    // ([label, type: text|money|number]), rows, totals (optional).
    // Reports reuse the same controller methods the app's screens call, so
    // file totals always match what's on screen.
    // ------------------------------------------------------------------

    private function saleReportData(Request $request): array
    {
        $report = $this->callJson(ReportController::class, 'sales', [
            'from_date' => $request['from_date'],
            'to_date' => $request['to_date'],
            'payment_status' => $request['payment_status'],
            'customer_id' => $request['customer_id'],
            'limit' => self::ALL_ROWS,
        ]);

        $rows = array_map(fn($row) => [
            $this->dateTime($row['created_at']),
            (string)$row['id'],
            $row['customer_name'],
            ucfirst($row['payment_status']),
            (float)$row['order_amount'],
            (float)$row['paid_amount'],
            (float)$row['balance'],
        ], $report['transactions']['data'] ?? []);

        $meta = ['Period' => $this->period($report['from_date'], $report['to_date'])];
        if ($request['payment_status']) {
            $meta['Payment status'] = ucfirst($request['payment_status']);
        }
        if ($request['customer_id'] && ($rows[0][2] ?? null)) {
            $meta['Party'] = $rows[0][2];
        }

        return [
            'title' => 'Sale Report',
            'filename' => 'sale-report_' . $report['from_date'] . '_' . $report['to_date'],
            'meta' => $meta,
            'summary' => [
                'No. of sales' => (string)$report['total_orders'],
                'Total sale' => $this->money($report['total_sales']),
                'Received' => $this->money($report['total_paid']),
                'Balance due' => $this->money($report['total_due']),
            ],
            'columns' => [
                ['Date', 'text'], ['Invoice', 'text'], ['Party', 'text'], ['Status', 'text'],
                ['Amount', 'money'], ['Received', 'money'], ['Balance', 'money'],
            ],
            'rows' => $rows,
            'totals' => ['Total', '', '', '', (float)$report['total_sales'], (float)$report['total_paid'], (float)$report['total_due']],
            // The Excel copy is a plain table: no shop/period/summary rows on top,
            // only Date, Invoice, Party, Amount, Balance, and no zero-amount sales.
            'xlsx' => ['plain' => true, 'keep' => [0, 1, 2, 4, 6], 'skip_zero' => 4],
        ];
    }

    private function purchaseReportData(Request $request): array
    {
        $report = $this->callJson(ReportController::class, 'purchases', [
            'from_date' => $request['from_date'],
            'to_date' => $request['to_date'],
            'supplier_id' => $request['supplier_id'],
            'limit' => self::ALL_ROWS,
        ]);

        $rows = array_map(fn($bill) => [
            $this->dateTime($bill['created_at']),
            $bill['reference_no'],
            $bill['supplier_name'] ?: 'Unknown supplier',
            (int)$bill['item_count'],
            (int)$bill['total_qty'],
            (float)$bill['total_amount'],
        ], $report['bills']['data'] ?? []);

        $meta = ['Period' => $this->period($report['from_date'], $report['to_date'])];
        if ($request['supplier_id'] && ($rows[0][2] ?? null)) {
            $meta['Supplier'] = $rows[0][2];
        }

        return [
            'title' => 'Purchase Report',
            'filename' => 'purchase-report_' . $report['from_date'] . '_' . $report['to_date'],
            'meta' => $meta,
            'summary' => [
                'No. of bills' => (string)$report['total_bills'],
                'Quantity' => number_format($report['total_qty']) . ' pcs',
                'Total purchase' => $this->money($report['total_amount']),
            ],
            'columns' => [
                ['Date', 'text'], ['Bill No.', 'text'], ['Supplier', 'text'],
                ['Items', 'number'], ['Qty', 'number'], ['Amount', 'money'],
            ],
            'rows' => $rows,
            'totals' => ['Total', '', '', '', (int)$report['total_qty'], (float)$report['total_amount']],
        ];
    }

    private function itemSalesData(Request $request): array
    {
        $report = $this->callJson(ReportController::class, 'itemSales', [
            'from_date' => $request['from_date'],
            'to_date' => $request['to_date'],
            'searchValue' => $request['searchValue'],
            'limit' => self::ALL_ROWS,
        ]);

        $rows = array_map(fn($row) => [
            $row['name'] ?? ('Item #' . $row['product_id']),
            (string)($row['code'] ?? ''),
            (int)$row['qty'],
            (float)$row['amount'],
            (float)$row['cost'],
            (float)$row['profit'],
        ], $report['items']['data'] ?? []);

        return [
            'title' => 'Item Sale Report',
            'filename' => 'item-sales_' . $report['from_date'] . '_' . $report['to_date'],
            'meta' => ['Period' => $this->period($report['from_date'], $report['to_date'])],
            'summary' => [
                'Qty sold' => number_format($report['total_qty']) . ' pcs',
                'Sale amount' => $this->money($report['total_amount']),
                'Profit' => $this->money($report['total_profit']),
            ],
            'columns' => [
                ['Item', 'text'], ['Code', 'text'], ['Qty', 'number'],
                ['Amount', 'money'], ['Cost', 'money'], ['Profit', 'money'],
            ],
            'rows' => $rows,
            'totals' => ['Total', '', (int)$report['total_qty'], (float)$report['total_amount'], null, (float)$report['total_profit']],
        ];
    }

    private function stockSummaryData(Request $request): array
    {
        $stockLimit = (int)(getWebConfig(name: 'stock_limit') ?? 10);
        $searchValue = $request['searchValue'];
        $stockFilter = $request['stock'];

        $products = DB::table('products')
            ->when($searchValue, function ($query) use ($searchValue) {
                $query->where(function ($query) use ($searchValue) {
                    $query->where('name', 'like', "%{$searchValue}%")->orWhere('code', 'like', "%{$searchValue}%");
                });
            })
            ->when($stockFilter === 'low', fn($query) => $query->where('product_type', 'physical')->where('current_stock', '<', $stockLimit))
            ->when($stockFilter === 'out', fn($query) => $query->where('product_type', 'physical')->where('current_stock', '<=', 0))
            ->orderBy($stockFilter ? 'current_stock' : 'name')
            ->get(['name', 'code', 'current_stock', 'purchase_price', 'unit_price']);

        $totalValue = 0.0;
        $totalQty = 0;
        $rows = [];
        foreach ($products as $p) {
            $value = max(0, (int)$p->current_stock) * (float)$p->purchase_price;
            $totalValue += $value;
            $totalQty += max(0, (int)$p->current_stock);
            $rows[] = [$p->name, (string)$p->code, (int)$p->current_stock, (float)$p->purchase_price, (float)$p->unit_price, $value];
        }

        $label = ['low' => 'Low stock items', 'out' => 'Out of stock items'][$stockFilter] ?? 'All items';

        return [
            'title' => 'Stock Summary',
            'filename' => 'stock-summary_' . now()->format('Y-m-d'),
            'meta' => ['As of' => now()->format('d/m/Y h:i A'), 'Showing' => $label],
            'summary' => [
                'No. of items' => number_format(count($rows)),
                'Units in stock' => number_format($totalQty),
                'Stock value' => $this->money($totalValue),
            ],
            'columns' => [
                ['Item', 'text'], ['Code', 'text'], ['Stock', 'number'],
                ['Purchase Price', 'money'], ['Sale Price', 'money'], ['Stock Value', 'money'],
            ],
            'rows' => $rows,
            'totals' => ['Total', '', $totalQty, null, null, round($totalValue, 2)],
        ];
    }

    private function profitLossData(Request $request): array
    {
        $report = $this->callJson(ReportController::class, 'profitLoss', [
            'from_date' => $request['from_date'],
            'to_date' => $request['to_date'],
        ]);
        $profit = (float)$report['profit'];

        return [
            'title' => 'Profit & Loss',
            'filename' => 'profit-loss_' . $report['from_date'] . '_' . $report['to_date'],
            'meta' => ['Period' => $this->period($report['from_date'], $report['to_date'])],
            'summary' => [],
            'columns' => [['Particulars', 'text'], ['Amount', 'money']],
            'rows' => [
                ['Sale (+)', (float)$report['revenue']],
                ['Cost of goods sold (-)', (float)$report['cost']],
            ],
            'totals' => [$profit < 0 ? 'Net Loss' : 'Net Profit', abs($profit)],
        ];
    }

    private function dayBookData(Request $request): array
    {
        $day = $request['date'] ?: now()->format('Y-m-d');
        $feed = $this->callJson(TransactionController::class, 'index', [
            'from_date' => $day,
            'to_date' => $day,
            'limit' => self::ALL_ROWS,
        ]);

        $labels = ['sale' => 'Sale', 'payment_in' => 'Payment-In', 'purchase' => 'Purchase'];
        $moneyIn = 0.0;
        $moneyOut = 0.0;
        $rows = [];
        foreach ($feed['data'] ?? [] as $t) {
            $total = (float)$t['total'];
            if ($t['type'] === 'sale') {
                $in = $total - (float)$t['balance'];
                $out = 0.0;
            } elseif ($t['type'] === 'payment_in') {
                $in = $total;
                $out = 0.0;
            } else {
                $in = 0.0;
                $out = $total;
            }
            $moneyIn += $in;
            $moneyOut += $out;
            $rows[] = [
                Carbon::parse($t['created_at'])->timezone(config('app.timezone'))->format('h:i A'),
                $labels[$t['type']] ?? $t['type'],
                $t['type'] === 'payment_in' ? '' : (string)$t['reference'],
                $t['party_name'],
                $total,
                $in,
                $out,
            ];
        }

        return [
            'title' => 'Day Book',
            'filename' => 'daybook_' . $day,
            'meta' => ['Date' => Carbon::parse($day)->format('d/m/Y')],
            'summary' => [
                'Transactions' => (string)count($rows),
                'Money in' => $this->money($moneyIn),
                'Money out' => $this->money($moneyOut),
            ],
            'columns' => [
                ['Time', 'text'], ['Type', 'text'], ['Ref.', 'text'], ['Party', 'text'],
                ['Total', 'money'], ['Money In', 'money'], ['Money Out', 'money'],
            ],
            'rows' => $rows,
            'totals' => ['Total', '', '', '', null, round($moneyIn, 2), round($moneyOut, 2)],
        ];
    }

    private function dueData(): array
    {
        $customers = DB::table('users')
            ->where('due_balance', '>', 0)
            ->orderByDesc('due_balance')
            ->get(['name', 'f_name', 'l_name', 'phone', 'due_balance']);

        $rows = [];
        $total = 0.0;
        foreach ($customers as $c) {
            $name = $c->name ?: trim($c->f_name . ' ' . $c->l_name);
            $total += (float)$c->due_balance;
            $rows[] = [$name ?: (string)$c->phone, (string)$c->phone, (float)$c->due_balance];
        }

        return [
            'title' => 'Receivables (Customer Due)',
            'filename' => 'customer-due_' . now()->format('Y-m-d'),
            'meta' => ['As of' => now()->format('d/m/Y h:i A')],
            'summary' => [
                'Customers with due' => (string)count($rows),
                "You'll get" => $this->money($total),
            ],
            'columns' => [['Customer', 'text'], ['Phone', 'text'], ['Due', 'money']],
            'rows' => $rows,
            'totals' => ['Total', '', round($total, 2)],
        ];
    }

    private function purchaseBillData(Request $request): ?array
    {
        $response = app(PurchaseController::class)->show((string)$request['reference']);
        if ($response->getStatusCode() !== 200) {
            return null;
        }
        $bill = $response->getData(true);

        $rows = array_map(fn($line) => [
            $line['product_name'] ?? ('Item #' . $line['product_id']),
            (string)($line['product_code'] ?? ''),
            (int)$line['qty'],
            (float)($line['unit_cost'] ?? 0),
            (float)$line['line_total'],
        ], $bill['items']);

        return [
            'title' => 'Purchase Bill',
            'filename' => 'purchase_' . preg_replace('/[^A-Za-z0-9_-]/', '-', $bill['reference_no']),
            'meta' => [
                'Bill No.' => $bill['reference_no'],
                'Date' => $this->dateTime($bill['created_at']),
                'Supplier' => $bill['supplier']['name'] ?? 'Unknown supplier',
                'Supplier phone' => $bill['supplier']['phone'] ?? '',
            ],
            'summary' => [],
            'columns' => [['Item', 'text'], ['Code', 'text'], ['Qty', 'number'], ['Unit Cost', 'money'], ['Amount', 'money']],
            'rows' => $rows,
            'totals' => ['Total', '', (int)$bill['total_qty'], null, (float)$bill['total_amount']],
        ];
    }

    private function invoiceData(Request $request): ?array
    {
        $order = Order::with(['customer', 'orderDetails'])->find($request['id']);
        if (!$order) {
            return null;
        }

        $rows = [];
        foreach ($order->orderDetails as $detail) {
            $product = is_string($detail->product_details) ? json_decode($detail->product_details, true) : (array)$detail->product_details;
            $rows[] = [
                $product['name'] ?? ('Item #' . $detail->product_id),
                (int)$detail->qty,
                (float)$detail->price,
                round((float)$detail->price * (int)$detail->qty, 2),
            ];
        }

        $customer = $order->customer;
        $customerName = $customer
            ? ($customer->name ?: trim($customer->f_name . ' ' . $customer->l_name))
            : 'Walking Customer';
        $total = (float)$order->order_amount;
        $paid = (float)$order->paid_amount;
        $due = max(0, round($total - $paid, 2));

        return [
            'view' => 'invoice',
            'title' => 'Invoice',
            'filename' => 'invoice_' . $order->id,
            'meta' => [
                'Invoice No.' => '#' . $order->id,
                'Date' => $this->dateTime($order->created_at),
                'Customer' => $customerName ?: 'Walking Customer',
                'Phone' => $order->customer_id ? (string)($customer->phone ?? '') : '',
                'Payment' => ucfirst((string)$order->payment_method),
            ],
            'summary' => [],
            'columns' => [['Item', 'text'], ['Qty', 'number'], ['Price', 'money'], ['Amount', 'money']],
            'rows' => $rows,
            'totals' => ['Total', array_sum(array_column($rows, 1)), null, $total],
            'payment' => [
                'Total' => $total,
                'Paid' => $paid,
                'Due' => $due,
            ],
            'status' => $order->payment_status,
            'note' => $order->order_note,
        ];
    }

    // ------------------------------------------------------------------
    // Writers
    // ------------------------------------------------------------------

    private function pdfResponse(array $data)
    {
        $html = view('file-exports.mobile.' . ($data['view'] ?? 'report'), [
            'data' => $data,
            'company' => $this->company(),
            'money' => fn($value) => $this->money($value),
        ])->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => A4Paper::MPDF_FORMAT,
            'default_font' => 'FreeSerif',
            'autoLangToFont' => true,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 14,
        ]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetFooter($this->company()['name'] . '||Page {PAGENO} of {nbpg}');
        $mpdf->WriteHTML($html);
        $content = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $data['filename'] . '.pdf"',
        ]);
    }

    private function xlsxResponse(array $data)
    {
        $data = $this->applyXlsxOverrides($data);
        $plain = !empty($data['xlsx']['plain']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $data['title']), 0, 31));
        $columnCount = count($data['columns']);
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
        $moneyFormat = '#,##0.00';

        $r = 1;
        if (!$plain) {
            $sheet->setCellValue("A{$r}", $this->company()['name']);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(14);
            $r++;
            $sheet->setCellValue("A{$r}", $data['title']);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12);
            $r++;
            foreach ($data['meta'] as $label => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $sheet->setCellValue("A{$r}", $label);
                $sheet->setCellValueExplicit("B{$r}", (string)$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $r++;
            }
            if (!empty($data['payment'])) {
                foreach ($data['payment'] as $label => $value) {
                    $sheet->setCellValue("A{$r}", $label);
                    $sheet->setCellValue("B{$r}", $value);
                    $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode($moneyFormat);
                    $r++;
                }
            }
            foreach ($data['summary'] as $label => $value) {
                $sheet->setCellValue("A{$r}", $label);
                $sheet->setCellValueExplicit("B{$r}", (string)$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $r++;
            }
            $r++;
        }

        $headerRow = $r;
        foreach ($data['columns'] as $i => [$label]) {
            $sheet->setCellValue([$i + 1, $r], $label);
        }
        $sheet->getStyle("A{$r}:{$lastColumn}{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}:{$lastColumn}{$r}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E8F1FC');
        $r++;

        $firstDataRow = $r;
        foreach ($data['rows'] as $row) {
            $this->writeXlsxRow($sheet, $r, $row, $data['columns']);
            $r++;
        }
        if (!empty($data['totals'])) {
            $this->writeXlsxRow($sheet, $r, $data['totals'], $data['columns']);
            $sheet->getStyle("A{$r}:{$lastColumn}{$r}")->getFont()->setBold(true);
            $r++;
        }

        foreach ($data['columns'] as $i => [, $type]) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            if ($type === 'money' && $r > $firstDataRow) {
                $sheet->getStyle("{$col}{$firstDataRow}:{$col}" . ($r - 1))->getNumberFormat()->setFormatCode($moneyFormat);
            }
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A' . ($headerRow + 1));
        A4Paper::applyToWorksheet($sheet);
        // Repeat the column headers at the top of every printed page.
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $data['filename'] . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Applies a dataset's Excel-only trimming: `skip_zero` drops rows whose value
     * in that column is 0, `keep` limits (and orders) the columns.
     */
    private function applyXlsxOverrides(array $data): array
    {
        $cfg = $data['xlsx'] ?? [];
        if (isset($cfg['skip_zero'])) {
            $col = $cfg['skip_zero'];
            $data['rows'] = array_values(array_filter($data['rows'], fn($row) => (float)($row[$col] ?? 0) != 0.0));
        }
        if (!empty($cfg['keep'])) {
            $pick = fn(array $row) => array_map(fn($i) => $row[$i] ?? null, $cfg['keep']);
            $data['columns'] = $pick($data['columns']);
            $data['rows'] = array_map($pick, $data['rows']);
            if (!empty($data['totals'])) {
                $data['totals'] = $pick($data['totals']);
            }
        }
        return $data;
    }

    private function writeXlsxRow($sheet, int $r, array $row, array $columns): void
    {
        foreach ($row as $i => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $type = $columns[$i][1] ?? 'text';
            if ($type === 'text' || !is_numeric($value)) {
                // Explicit strings keep phone numbers / codes like "0171..." intact.
                $sheet->setCellValueExplicit([$i + 1, $r], (string)$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue([$i + 1, $r], $value);
                if ($type === 'number') {
                    $sheet->getStyle([$i + 1, $r])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function callJson(string $controller, string $method, array $query): array
    {
        $query = array_filter($query, fn($value) => $value !== null && $value !== '');
        return app($controller)->{$method}(new Request($query))->getData(true);
    }

    private function company(): array
    {
        return [
            'name' => getWebConfig(name: 'company_name') ?: config('app.name'),
            'phone' => getWebConfig(name: 'company_phone'),
            'email' => getWebConfig(name: 'company_email'),
            'address' => getWebConfig(name: 'shop_address'),
        ];
    }

    /** "৳ 10,81,865.00" — lakh/crore grouping, same as the app. */
    public function money($value): string
    {
        $value = round((float)$value, 2);
        $negative = $value < 0;
        [$whole, $fraction] = explode('.', number_format(abs($value), 2, '.', ''));
        if (strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $whole = $rest . ',' . $last3;
        }
        return ($negative ? '-' : '') . '৳ ' . $whole . '.' . $fraction;
    }

    private function period(string $from, string $to): string
    {
        return Carbon::parse($from)->format('d/m/Y') . ' to ' . Carbon::parse($to)->format('d/m/Y');
    }

    private function dateTime($value): string
    {
        return $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('d/m/Y h:i A') : '';
    }
}
