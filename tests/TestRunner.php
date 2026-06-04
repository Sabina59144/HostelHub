<?php
/**
 * TestRunner.php
 * ──────────────────────────────────────────────────────────────
 * Lightweight test runner for the Student Module validation suite.
 * Provides assertion helpers and collects results for HTML output.
 * ──────────────────────────────────────────────────────────────
 */

class TestRunner {
    private array $results  = [];
    private array $sections = [];
    private string $current = '';
    private int $pass = 0;
    private int $fail = 0;

    /** Start a named section (maps to one Portfolio 2 test log). */
    public function section(string $name): void {
        $this->current = $name;
        $this->sections[$name] = [];
    }

    /**
     * Run one test case.
     *
     * @param string $testType    Boundary label (e.g. "Min (Boundary)")
     * @param string $testData    Input description
     * @param string $expected    Expected outcome text
     * @param array  $actual      Return from a validate_*() function
     * @param bool   $expectValid Whether the input should pass validation
     */
    public function run(
        string $testType,
        string $testData,
        string $expected,
        array  $actual,
        bool   $expectValid
    ): void {
        $passed = ($actual['valid'] === $expectValid);
        $label  = $passed ? 'PASS' : 'FAIL';

        $row = [
            'type'     => $testType,
            'data'     => $testData,
            'expected' => $expected,
            'actual'   => $actual['message'],
            'passed'   => $passed,
            'label'    => $label,
        ];

        $this->sections[$this->current][] = $row;
        $this->results[] = $row;
        $passed ? $this->pass++ : $this->fail++;
    }

    public function getTotals(): array {
        return ['pass' => $this->pass, 'fail' => $this->fail,
                'total' => $this->pass + $this->fail];
    }

    public function getSections(): array {
        return $this->sections;
    }
}
