<?php

namespace Tests\Feature;

use App\Console\Commands\CalculateAndUpdateMovingAverages;
use Tests\TestCase;

class CalculateAndUpdateMovingAveragesTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testCalculateMovingAverages()
    {
        $sheetValues = [
            ['Date', 'Visitors'],
            ['2023-08-01', 5000],
            ['2023-08-02', 2000],
            ['2023-08-03', 1500],
            ['2023-08-04', 1003],
            ['2023-08-05', 1345],
            ['2023-08-06', 8905],
            ['2023-08-07', 1200],
            ['2023-08-08', 1325]
        ];
        $movingAverages = (new CalculateAndUpdateMovingAverages())->calculateMovingAverages($sheetValues, 4);
        $expectedMovingAverages = [
            [2125],
            [2375.75],
            [1462],
            [3188.25],
            [3113.25],
            [3193.75]
        ];
        $this->assertEquals($expectedMovingAverages, $movingAverages);
    }

    /**
     * @return void
     */
    public function testSheetNameNotExists()
    {
        $nonExistentSheetName = 'dummy-moving-avg-sheet';
        $this->artisan('calculate:moving-visitors-averages', [
            '--sheetName' => 'dummy-moving-avg-sheet',
            '--windowSize' => 3,
        ])->expectsOutput('Error: Sheet with name "' . $nonExistentSheetName . '" does not exist.')
            ->assertExitCode(0);
    }

    /**
     * @return void
     */
    public function testSheetNameExist()
    {
        $this->artisan('calculate:moving-visitors-averages', [
            '--sheetName' => 'moving_average',
            '--windowSize' => 3,
        ])->expectsOutput('Moving averages calculated and updated successfully.')
            ->assertExitCode(0);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testCalculateMovingAveragesWithEmptyValues()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid data or window size.');
        (new CalculateAndUpdateMovingAverages())->calculateMovingAverages([], 10);
    }
}
