<?php
/**
 * TestRunner.php
 * -----------------------------------------------------------------------------
 * Lightweight test runner for the HostelHub User Module validation suite.
 * Provides assertion helpers and collects results for HTML output.
 * -----------------------------------------------------------------------------
 */

class TestRunner {
    private array $results = [];
    private array $sections = [];
    private string $current = '';
    private int $pass = 0;
    private int $fail = 0;

    /**
     * Start a named section. Each section maps to one Portfolio 2 test log.
     */
    public function section(string $name): void {
        $this->current = $name;
        if (!isset($this->sections[$name])) {
            $this->sections[$name] = [];
        }
    }

    /**
     * Run one validation test case.
     *
     * @param string $testType     Boundary label, e.g. Extreme Min, Min -1, Min, Extreme Max.
     * @param string $testData     Input data description.
     * @param string $expected     Expected result text.
     * @param array  $actual       Return from validation function: ['valid'=>bool, 'message'=>string]
     * @param bool   $expectValid  Whether the input should pass validation.
     */
    public function run(
        string $testType,
        string $testData,
        string $expected,
        array $actual,
        bool $expectValid
    ): void {
        $actualValid = (bool)($actual['valid'] ?? false);
        $actualMessage = (string)($actual['message'] ?? '');

        $passed = ($actualValid === $expectValid);

        if ($passed) {
            $this->pass++;
        } else {
            $this->fail++;
        }

        $row = [
            'section' => $this->current,
            'testType' => $testType,
            'testData' => $testData,
            'expected' => $expected,
            'actual' => $actualMessage,
            'status' => $passed ? 'PASS' : 'FAIL'
        ];

        $this->results[] = $row;
        $this->sections[$this->current][] = $row;
    }

    /**
     * Display all test results as HTML tables.
     */
    public function render(): void {
        echo "<!DOCTYPE html>";
        echo "<html lang='en'>";
        echo "<head>";
        echo "<meta charset='UTF-8'>";
        echo "<title>HostelHub User Module Test Scripts</title>";
        echo "<style>
            body {
                font-family: Arial, sans-serif;
                background: #f4f6f8;
                margin: 30px;
                color: #111827;
            }
            h1 {
                margin-bottom: 5px;
            }
            .summary {
                background: #ffffff;
                border: 1px solid #d1d5db;
                padding: 15px;
                margin-bottom: 25px;
                border-radius: 8px;
            }
            .section {
                margin-bottom: 35px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                background: #ffffff;
                margin-top: 10px;
            }
            th, td {
                border: 1px solid #d1d5db;
                padding: 9px;
                text-align: left;
                font-size: 14px;
            }
            th {
                background: #e5e7eb;
            }
            .pass {
                color: green;
                font-weight: bold;
            }
            .fail {
                color: red;
                font-weight: bold;
            }
            .note {
                background: #fff7ed;
                border: 1px solid #fdba74;
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 20px;
            }
        </style>";
        echo "</head>";
        echo "<body>";

        echo "<h1>HostelHub User Module Test Scripts</h1>";
        echo "<div class='summary'>";
        echo "<strong>Total Passed:</strong> {$this->pass}<br>";
        echo "<strong>Total Failed:</strong> {$this->fail}<br>";
        echo "<strong>Total Tests:</strong> " . count($this->results);
        echo "</div>";

        echo "<div class='note'>";
        echo "These tests support Portfolio 2 by showing validation testing for each users table data type: VARCHAR, ENUM, BOOLEAN/TINYINT, INTEGER and DATETIME.";
        echo "</div>";

        foreach ($this->sections as $section => $rows) {
            echo "<div class='section'>";
            echo "<h2>" . htmlspecialchars($section) . "</h2>";
            echo "<table>";
            echo "<tr>";
            echo "<th>Test Type</th>";
            echo "<th>Test Data</th>";
            echo "<th>Expected Result</th>";
            echo "<th>Actual Result</th>";
            echo "<th>Status</th>";
            echo "</tr>";

            foreach ($rows as $row) {
                $class = strtolower($row['status']);
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['testType']) . "</td>";
                echo "<td>" . htmlspecialchars($row['testData']) . "</td>";
                echo "<td>" . htmlspecialchars($row['expected']) . "</td>";
                echo "<td>" . htmlspecialchars($row['actual']) . "</td>";
                echo "<td class='{$class}'>" . htmlspecialchars($row['status']) . "</td>";
                echo "</tr>";
            }

            echo "</table>";
            echo "</div>";
        }

        echo "</body>";
        echo "</html>";
    }
}
?>