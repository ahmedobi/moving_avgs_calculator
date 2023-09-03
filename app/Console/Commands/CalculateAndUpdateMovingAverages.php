<?php

namespace App\Console\Commands;

use Google\Exception;
use Google\Service\Sheets\ClearValuesRequest;
use Google_Service_Sheets_ValueRange;
use Illuminate\Console\Command;
use Google_Client;
use Google_Service_Sheets;

class CalculateAndUpdateMovingAverages extends Command
{
    protected $signature = 'calculate:moving-visitors-averages {--sheetName=} {--windowSize=}';
    protected $description = 'Calculate moving averages and update the "Moving Average" column in Google Sheets';

    /**
     * @var Google_Client
     */
    protected $client;

    /**
     * @var Google_Service_Sheets
     */
    protected $sheetsService;

    /**
     * @throws Exception
     */
    private function getClient(): Google_Client
    {
        if (!$this->client) {
            $this->client = new Google_Client();
            $this->client->setAuthConfig(config('app.google_sheets.google_sheets_credentials_path'));
            $this->client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
        }

        return $this->client;
    }

    /**
     * @throws Exception
     */
    private function getSheetsService(): Google_Service_Sheets
    {
        if (!$this->sheetsService) {
            $this->sheetsService = new Google_Service_Sheets($this->getClient());
        }

        return $this->sheetsService;
    }

    /**
     * Description:
     * This command for calculating moving averages and updating the "Moving Average" column in Google Sheets.
     *
     * Usage:
     * php artisan calculate:moving-visitors-averages {--sheetName=} {--windowSize=}
     *
     * Options:
     *  --help  Display this help message
     *  --sheetName  The name of the sheet in Google Sheets, default value is "Sheet1"
     *  --windowSize  The window size for calculating moving averages, default value is the number of rows in the sheet
     *
     * Examples:
     * Php artisan calculate:moving-visitors-averages --sheetName=moving_average --windowSize=3
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->info('Starting to calculate moving averages...');

            $spreadsheetId = config('app.google_sheets.spreadsheet_id');
            $sheetName = $this->getSheetName();

            if (!$this->checkIfSheetExists($this->getSheetsService()->spreadsheets->get($spreadsheetId)->getSheets(), $sheetName)) {
                throw new \Exception('Sheet with name "' . $sheetName . '" does not exist.');
            }

            $values = $this->getSheetData($sheetName);

            $windowSize = $this->getWindowSize(count($values));
            $movingAverages = $this->calculateMovingAverages($values, $windowSize);

            $this->updateMovingAverages($this->getSheetsService(), $spreadsheetId, $sheetName, $windowSize, $values, $movingAverages);

            $this->info('Moving averages calculated and updated successfully.');
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * @return string
     */
    public function getSheetName(): string
    {
        return $this->option('sheetName') ?? 'Sheet1';
    }

    /**
     * @param $sheetRowsCount
     * @return int
     */
    public function getWindowSize($sheetRowsCount): int
    {
        return $this->option('windowSize') ?? count($sheetRowsCount);
    }

    /**
     * @param $sheets
     * @param $sheetName
     * @return bool
     */
    public function checkIfSheetExists($sheets, $sheetName): bool
    {
        foreach ($sheets as $sheet) {
            if ($sheet->getProperties()->getTitle() === $sheetName) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $sheetsService
     * @param $spreadsheetId
     * @param $sheetName
     * @param $windowSize
     * @param $values
     * @param $movingAverages
     * @return void
     */
    public function updateMovingAverages($sheetsService, $spreadsheetId, $sheetName, $windowSize, $values, $movingAverages): void
    {
        if (!in_array("Moving Average", $values[0])) {
            $this->createMovingAverageColumnIfNotExist($sheetsService, $spreadsheetId, $sheetName);
        }
        $updateRange = $sheetName . '!C' . ($windowSize + 1) . ':C' . (count($values) + 1);
        $clearRange = $sheetName . '!C2' . ':C' . (count($values) + 1);
        $sheetsService->spreadsheets_values->clear($spreadsheetId, $clearRange, new ClearValuesRequest());
        $updateValues = $movingAverages;
        $updateBody = new Google_Service_Sheets_ValueRange([
            'values' => $updateValues
        ]);
        $updateParams = [
            'valueInputOption' => 'RAW'
        ];

        $sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            $updateRange,
            $updateBody,
            $updateParams
        );
    }

    /**
     * @param $sheetsService
     * @param $spreadsheetId
     * @param $sheetName
     * @return void
     */
    public function createMovingAverageColumnIfNotExist($sheetsService, $spreadsheetId, $sheetName): void
    {
        $updateRange = $sheetName . '!C1';
        $updateValues = [['Moving Average']];
        $updateBody = new Google_Service_Sheets_ValueRange([
            'values' => $updateValues
        ]);
        $updateParams = [
            'valueInputOption' => 'RAW'
        ];

        $sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            $updateRange,
            $updateBody,
            $updateParams
        );
    }

    /**
     * @param $values
     * @param $windowSize
     * @return array
     * @throws \Exception
     */
    public function calculateMovingAverages($values, $windowSize): array
    {
        if (count($values) < $windowSize || $windowSize <= 0) {
            throw new \Exception('Invalid data or window size.');
        }
        $movingAverages = [];
        $headers = $values[0];
        for ($i = $windowSize - 1; $i < count($values); $i++) {
            $sum = 0;
            for ($j = $i - $windowSize + 1; $j <= $i; $j++) {
                $sum += (int)$values[$j][array_search("Visitors", $headers)];
            }
            $movingAverage = $sum / $windowSize;
            $movingAverages[] = [$movingAverage];
        }
        return $movingAverages;
    }

    /**
     * @throws Exception
     */
    public function getSheetData($sheetName): array
    {
        $spreadsheetId = config('app.google_sheets.spreadsheet_id');
        $response = $this->getSheetsService()->spreadsheets_values->get($spreadsheetId, $sheetName . '!A:B');
        $values = $response->getValues();
        if ($this->validateValues($values)) {
            return $values;
        } else {
            throw new \Exception('Invalid Data.');
        }
    }

    /**
     * @param $values
     * @return bool
     */
    public function validateValues($values): bool
    {
        if (empty($values)) {
            return false;
        }

        $headers = $values[0];
        $dateColumnIndex = array_search("Date", $headers);
        $visitorsColumnIndex = array_search("Visitors", $headers);

        if ($dateColumnIndex === false || $visitorsColumnIndex === false) {
            return false;
        }

        for ($i = 1; $i < count($values); $i++) {
            if (count($values[$i]) != 2) {
                return false;
            }
            if (!is_numeric($values[$i][$visitorsColumnIndex]) || $values[$i][$visitorsColumnIndex] < 0) {
                return false;
            }
            if (!strtotime($values[$i][$dateColumnIndex]) || strtotime($values[$i][$dateColumnIndex]) > time()) {
                return false;
            }
            if ($i > 1 && strtotime($values[$i][$dateColumnIndex]) < strtotime($values[$i - 1][$dateColumnIndex])) {
                return false;
            }
            if ($i > 1 && strtotime($values[$i][$dateColumnIndex]) - strtotime($values[$i - 1][$dateColumnIndex]) != 86400) {
                return false;
            }
        }
        return true;
    }
}
