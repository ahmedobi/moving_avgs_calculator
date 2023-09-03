# Moving average calculator

This is a project to calculate the moving average for website visitors using a command line, based on a Google sheet file.

## Installation

To get started, follow these steps:

1. Clone this repository
2. Install Composer dependencies: `composer install`
3. Enable the Google Sheets API
4. Create a service account and download the JSON file with the credentials
5. Move the JSON file to be under `storage/app` and rename it to `google-sheets-credentials.json`
6. Share the Google sheet with the email address from the service account
7. Get the sheet ID from the URL
8. Configure your `.env` file. You can copy the `.env.example` file: `cp .env.example .env`

## Usage

- In the command line, you can run the following command to calculate the moving average for the last 5 days, considering you will use the default sheet name:
`php artisan calculate:moving-visitors-averages --windowSize=5`

- In the command line, you can run the following command to calculate the moving average for the last 5 days, considering you will use a custom sheet name:
`php artisan calculate:moving-visitors-averages --windowSize=5 --sheetName=dummy-moving-avg-sheet`

- In the command line, you can run the following command to calculate the moving average for the whole sheet, considering you will use the default sheet name:
`php artisan calculate:moving-visitors-averages`
