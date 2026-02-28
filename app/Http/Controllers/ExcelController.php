<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelController extends Controller
{
    public function loginForm()
    {
        if (session('access_granted')) {
            return redirect('/');
        }
        return view('login');
    }

    public function login(Request $request)
    {
        $request->validate(['password' => 'required']);

        if ($request->password === config('app.access_password')) {
            session(['access_granted' => true]);
            return redirect('/');
        }

        return back()->withErrors(['password' => 'Clave incorrecta.']);
    }

    public function index()
    {
        return view('upload');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('access_granted');
        return redirect()->route('login');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:4',
        ]);

        if ($request->current_password !== config('app.access_password')) {
            return response()->json(['error' => 'Clave actual incorrecta.'], 422);
        }

        // Actualizar el .env
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);
        $envContent = preg_replace(
            '/^APP_ACCESS_PASSWORD=.*/m',
            'APP_ACCESS_PASSWORD=' . $request->new_password,
            $envContent
        );
        file_put_contents($envPath, $envContent);

        // Limpiar cache de config para que tome el nuevo valor
        \Artisan::call('config:clear');

        return response()->json(['success' => true]);
    }

    public function process(Request $request)
    {
        $request->validate([
            'excel' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('excel');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        // Leer headers y clasificar columnas
        $headers = [];
        $phoneCols = [];       // todas las columnas de teléfono
        $phoneColTypes = [];   // col => 'TT' o 'CD'
        $empresaCol = null;
        $cedulaCol = null;
        $numOblCol = null;
        $smsTtCol = null;
        $whatsappCol = null;
        $smsCdCol = null;

        for ($colIndex = 1; $colIndex <= $highestColIndex; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $headerRaw = strtoupper(trim($sheet->getCell($col . '1')->getValue() ?? ''));
            // Quitar tildes para comparación
            $header = $this->removeTildes($headerRaw);
            $headers[$col] = $headerRaw;

            if (str_contains($header, 'EMPRESA')) $empresaCol = $col;
            if (str_contains($header, 'CEDULA')) $cedulaCol = $col;
            if (str_contains($header, 'NUMERO') && str_contains($header, 'OBL')) $numOblCol = $col;
            if (preg_match('/SMS[_ ]?TT/', $header)) $smsTtCol = $col;
            if (str_contains($header, 'WHATSAPP')) $whatsappCol = $col;
            if (preg_match('/SMS[_ ]?CD/', $header)) $smsCdCol = $col;

            if (str_contains($header, 'TELEFONO') || str_contains($header, 'CELULAR') || str_contains($header, 'TEL')) {
                $phoneCols[] = $col;
                if (str_contains($header, '_CD') || str_contains($header, ' CD')) {
                    $phoneColTypes[$col] = 'CD';
                } else {
                    $phoneColTypes[$col] = 'TT';
                }
            }
        }

        // ============================================================
        // ARCHIVO 1: Excel sin duplicados (teléfonos repetidos en 0)
        // ============================================================
        $seen = [];
        $duplicates = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            foreach ($phoneCols as $col) {
                $value = trim($sheet->getCell($col . $row)->getValue() ?? '');
                if ($value === '' || $value === '0') continue;
                if (!isset($seen[$value])) {
                    $seen[$value] = ['row' => $row, 'col' => $col];
                } else {
                    $duplicates[] = ['row' => $row, 'col' => $col];
                }
            }
        }

        foreach ($duplicates as $dup) {
            $sheet->setCellValue($dup['col'] . $dup['row'], 0);
        }

        $writer1 = new Xlsx($spreadsheet);
        $tempFile1 = tempnam(sys_get_temp_dir(), 'excel1_') . '.xlsx';
        $writer1->save($tempFile1);

        // ============================================================
        // ARCHIVO 2: Reporte detallado (una fila por teléfono único)
        // ============================================================

        // Recargar el original para no usar el modificado
        $spreadsheetOrig = IOFactory::load($file->getPathname());
        $sheetOrig = $spreadsheetOrig->getActiveSheet();

        // Recolectar datos por fila
        $reportRows = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $empresa = $empresaCol ? trim($sheetOrig->getCell($empresaCol . $row)->getValue() ?? '') : '';
            $cedula = $cedulaCol ? trim($sheetOrig->getCell($cedulaCol . $row)->getValue() ?? '') : '';
            $numObl = $numOblCol ? trim($sheetOrig->getCell($numOblCol . $row)->getValue() ?? '') : '';
            $smsTt = $smsTtCol ? trim($sheetOrig->getCell($smsTtCol . $row)->getValue() ?? '') : '';
            $whatsapp = $whatsappCol ? trim($sheetOrig->getCell($whatsappCol . $row)->getValue() ?? '') : '';
            $smsCd = $smsCdCol ? trim($sheetOrig->getCell($smsCdCol . $row)->getValue() ?? '') : '';
            $codRefUnica = $cedula . '-' . $numObl;

            // Recolectar teléfonos únicos de esta fila
            $phonesInRow = [];
            foreach ($phoneCols as $col) {
                $phone = trim($sheetOrig->getCell($col . $row)->getValue() ?? '');
                if ($phone === '' || $phone === '0') continue;
                $type = $phoneColTypes[$col] ?? 'TT';
                $calidad = $type === 'CD' ? 'CODEUDOR_CD' : 'TITULAR_TT';

                // Evitar duplicados dentro de la misma fila
                if (!isset($phonesInRow[$phone])) {
                    $phonesInRow[$phone] = $calidad;
                }
            }

            foreach ($phonesInRow as $phone => $calidad) {
                $reportRows[] = [
                    'empresa' => $empresa,
                    'cedula' => $cedula,
                    'numero_obl' => $numObl,
                    'sms_tt' => $smsTt,
                    'whatsapp' => $whatsapp,
                    'sms_cd' => $smsCd,
                    'telefono' => $phone,
                    'calidad' => $calidad,
                    'largo' => strlen((string)$phone),
                    'cod_ref_unica' => $codRefUnica,
                ];
            }
        }

        // Contadores secuenciales de teléfono y cod_referencia_unica
        $phoneSeq = [];
        $codRefSeq = [];

        // Crear el segundo Excel
        $spreadsheet2 = new Spreadsheet();
        $sheet2 = $spreadsheet2->getActiveSheet();

        // Headers
        $reportHeaders = [
            'EMPRESA', 'CEDULA', 'NUMERO_OBL', 'SMS_TT', 'WHATSAPP_MMS',
            'SMS_CD', 'TELEFONO_1_TT', 'CALIDAD_TERCERO', 'LARGO',
            'CONTAR_REPETIDOS_#', 'COD_REFERENCIA_UNICA', 'CONTAR_REPETIDOS_OBL'
        ];
        foreach ($reportHeaders as $i => $h) {
            $sheet2->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '1', $h);
        }

        // Datos
        $rowNum = 2;
        foreach ($reportRows as $r) {
            $sheet2->setCellValueExplicit('A' . $rowNum, $r['empresa'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit('B' . $rowNum, $r['cedula'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit('C' . $rowNum, $r['numero_obl'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit('D' . $rowNum, $r['sms_tt'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit('E' . $rowNum, $r['whatsapp'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit('F' . $rowNum, $r['sms_cd'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit('G' . $rowNum, $r['telefono'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet2->setCellValue('H' . $rowNum, $r['calidad']);
            $sheet2->setCellValue('I' . $rowNum, $r['largo']);

            $phoneSeq[$r['telefono']] = ($phoneSeq[$r['telefono']] ?? 0) + 1;
            $sheet2->setCellValue('J' . $rowNum, $phoneSeq[$r['telefono']]);

            $sheet2->setCellValueExplicit('K' . $rowNum, $r['cod_ref_unica'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $codRefSeq[$r['cod_ref_unica']] = ($codRefSeq[$r['cod_ref_unica']] ?? 0) + 1;
            $sheet2->setCellValue('L' . $rowNum, $codRefSeq[$r['cod_ref_unica']]);
            $rowNum++;
        }

        $writer2 = new Xlsx($spreadsheet2);
        $tempFile2 = tempnam(sys_get_temp_dir(), 'excel2_') . '.xlsx';
        $writer2->save($tempFile2);

        // ============================================================
        // Empaquetar ambos archivos en un ZIP
        // ============================================================
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $zipFile = tempnam(sys_get_temp_dir(), 'zip_') . '.zip';

        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE);
        $zip->addFile($tempFile1, $originalName . '_sin_duplicados.xlsx');
        $zip->addFile($tempFile2, $originalName . '_reporte_detallado.xlsx');
        $zip->close();

        // Limpiar temporales
        @unlink($tempFile1);
        @unlink($tempFile2);

        return response()->download($zipFile, $originalName . '_procesado.zip')->deleteFileAfterSend(true);
    }

    private function removeTildes(string $str): string
    {
        $search  = ['Á','É','Í','Ó','Ú','Ñ','á','é','í','ó','ú','ñ'];
        $replace = ['A','E','I','O','U','N','a','e','i','o','u','n'];
        return str_replace($search, $replace, $str);
    }
}
