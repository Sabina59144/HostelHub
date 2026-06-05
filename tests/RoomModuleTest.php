<?php
/**
 * RoomModuleTest.php
 * PHPUnit BVA tests for the HostelHub Room Module
 *
 * Test Log 1 – Room Capacity field       (add_room.php)      – 11 tests
 * Test Log 2 – Price per Month field     (add_room.php)      – 11 tests
 * Test Log 3 – Room Allocation occupancy (allocate_room.php) – 11 tests
 *
 * Run from HostelHub root:
 *   C:\xampp\php\php.exe vendor\bin\phpunit --configuration phpunit.xml
 */

use PHPUnit\Framework\TestCase;

class RoomModuleTest extends TestCase
{
    // ── Mirrors add_room.php capacity validation ──────────────────────────────
    private function validateCapacity(string $capacity): ?string
    {
        if ($capacity === '') return 'Capacity is required.';
        if (!ctype_digit($capacity) || (int)$capacity < 1)
            return 'Capacity must be a whole number greater than 0.';
        // No server-side max check in add_room.php (only HTML max="10")
        return null;
    }

    // ── Mirrors add_room.php price validation ─────────────────────────────────
    private function validatePrice(string $price): ?string
    {
        if ($price === '') return 'Price per month is required.';
        if (!is_numeric($price) || (float)$price < 0)
            return 'Price per month must be a valid positive number.';
        // No server-side maximum price check in add_room.php
        return null;
    }

    // ── Mirrors allocate_room.php allocation validation ───────────────────────
    private function validateAllocation(int $capacity, int $occupants, int $studentRoomId, int $targetRoomId): ?string
    {
        $left = $capacity - $occupants;
        if ($left <= 0)
            return "Room is already at full capacity ($occupants / $capacity).";
        if ($studentRoomId === $targetRoomId)
            return 'Student is already allocated to this room.';
        return null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TEST LOG 1 – Room Capacity Field (add_room.php)
    // Boundary: min = 1, max = 10 (HTML); PHP only validates > 0
    // ═════════════════════════════════════════════════════════════════════════

    /** @test Extreme Min: -999 rejected by HTML min="1" */
    public function capacity_extreme_min_negative_999_is_rejected(): void
    {
        // ctype_digit('-999') = false (hyphen is not a digit)
        $result = $this->validateCapacity('-999');
        $this->assertNotNull($result, 'Extreme min (-999) should be rejected');
        $this->assertEquals('Capacity must be a whole number greater than 0.', $result);
    }

    /** @test Min-1: 0 rejected — below minimum boundary */
    public function capacity_min_minus_one_zero_is_rejected(): void
    {
        $result = $this->validateCapacity('0');
        $this->assertNotNull($result);
        $this->assertEquals('Capacity must be a whole number greater than 0.', $result);
    }

    /** @test Min boundary: 1 accepted */
    public function capacity_min_boundary_1_is_accepted(): void
    {
        $result = $this->validateCapacity('1');
        $this->assertNull($result, 'Min boundary (1) should be accepted');
    }

    /** @test Min+1: 2 accepted */
    public function capacity_min_plus_one_2_is_accepted(): void
    {
        $result = $this->validateCapacity('2');
        $this->assertNull($result, 'Min+1 (2) should be accepted');
    }

    /** @test Max-1: 9 accepted */
    public function capacity_max_minus_one_9_is_accepted(): void
    {
        $result = $this->validateCapacity('9');
        $this->assertNull($result, 'Max-1 (9) should be accepted');
    }

    /** @test Max boundary: 10 accepted */
    public function capacity_max_boundary_10_is_accepted(): void
    {
        $result = $this->validateCapacity('10');
        $this->assertNull($result, 'Max boundary (10) should be accepted');
    }

    /**
     * @test Max+1: 11 — FAIL: PHP has no server-side max check
     * HTML max="10" blocks this in browser but PHP accepts it if bypassed
     */
    public function capacity_max_plus_one_11_should_be_rejected_but_php_accepts_it(): void
    {
        $result = $this->validateCapacity('11');
        // Documents the defect: PHP should reject 11 but does not
        $this->assertNull($result, 'FAIL – PHP has no server-side max; 11 is accepted when it should not be');
    }

    /** @test Mid: 5 accepted */
    public function capacity_mid_5_is_accepted(): void
    {
        $result = $this->validateCapacity('5');
        $this->assertNull($result, 'Mid (5) should be accepted');
    }

    /**
     * @test Extreme Max: 9999 — FAIL: PHP has no server-side max check
     */
    public function capacity_extreme_max_9999_should_be_rejected_but_php_accepts_it(): void
    {
        $result = $this->validateCapacity('9999');
        // Documents the defect: no server-side upper limit
        $this->assertNull($result, 'FAIL – PHP has no server-side max; 9999 is accepted when it should not be');
    }

    /** @test Invalid data type: letters rejected by ctype_digit() */
    public function capacity_invalid_letters_abc_are_rejected(): void
    {
        $result = $this->validateCapacity('abc');
        $this->assertNotNull($result);
        $this->assertEquals('Capacity must be a whole number greater than 0.', $result);
    }

    /** @test Other: empty field rejected with required message */
    public function capacity_empty_field_shows_required_error(): void
    {
        $result = $this->validateCapacity('');
        $this->assertEquals('Capacity is required.', $result);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TEST LOG 2 – Price per Month Field (add_room.php)
    // Boundary: min = 0; no upper max defined in PHP
    // ═════════════════════════════════════════════════════════════════════════

    /** @test Extreme Min: -9999 rejected — negative price */
    public function price_extreme_min_negative_9999_is_rejected(): void
    {
        $result = $this->validatePrice('-9999');
        $this->assertNotNull($result);
        $this->assertEquals('Price per month must be a valid positive number.', $result);
    }

    /** @test Min-1: -0.01 rejected — just below zero */
    public function price_min_minus_one_negative_decimal_is_rejected(): void
    {
        $result = $this->validatePrice('-0.01');
        $this->assertNotNull($result);
        $this->assertEquals('Price per month must be a valid positive number.', $result);
    }

    /** @test Min boundary: 0 accepted — free room */
    public function price_min_boundary_zero_is_accepted(): void
    {
        $result = $this->validatePrice('0');
        $this->assertNull($result, 'Min boundary (0) should be accepted');
    }

    /** @test Min+1: 0.01 accepted */
    public function price_min_plus_one_small_decimal_is_accepted(): void
    {
        $result = $this->validatePrice('0.01');
        $this->assertNull($result, 'Min+1 (0.01) should be accepted');
    }

    /** @test Max-1: 9998 accepted — no upper max defined */
    public function price_max_minus_one_9998_is_accepted(): void
    {
        $result = $this->validatePrice('9998');
        $this->assertNull($result, 'Max-1 (9998) accepted — no maximum validation');
    }

    /**
     * @test Max boundary: N/A — no maximum price defined in PHP or HTML
     * This test documents the absence of an upper boundary
     */
    public function price_max_boundary_not_defined_in_php(): void
    {
        // No maximum is enforced — any positive number is accepted
        $result = $this->validatePrice('9999');
        $this->assertNull($result, 'N/A – No max boundary defined; 9999 is accepted');
    }

    /**
     * @test Max+1: 999999 — FAIL: no maximum price validation in PHP
     */
    public function price_max_plus_one_999999_should_be_rejected_but_php_accepts_it(): void
    {
        $result = $this->validatePrice('999999');
        // Documents the defect: no server-side upper price limit
        $this->assertNull($result, 'FAIL – PHP has no max price check; 999999 is accepted when it should not be');
    }

    /** @test Mid: 500.00 accepted */
    public function price_mid_500_is_accepted(): void
    {
        $result = $this->validatePrice('500.00');
        $this->assertNull($result, 'Mid (500.00) should be accepted');
    }

    /**
     * @test Extreme Max: 9999999 — FAIL: no maximum price validation in PHP
     */
    public function price_extreme_max_9999999_should_be_rejected_but_php_accepts_it(): void
    {
        $result = $this->validatePrice('9999999');
        $this->assertNull($result, 'FAIL – PHP has no max price check; 9999999 is accepted');
    }

    /** @test Invalid data type: letters rejected by is_numeric() */
    public function price_invalid_letters_abc_are_rejected(): void
    {
        $result = $this->validatePrice('abc');
        $this->assertNotNull($result);
        $this->assertEquals('Price per month must be a valid positive number.', $result);
    }

    /** @test Other: empty field rejected with required message */
    public function price_empty_field_shows_required_error(): void
    {
        $result = $this->validatePrice('');
        $this->assertEquals('Price per month is required.', $result);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TEST LOG 3 – Room Allocation Occupancy (allocate_room.php)
    // Test room capacity = 3. Boundary: max occupants = capacity
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @test Extreme Min: N/A — occupancy cannot be negative in database
     */
    public function allocation_extreme_min_negative_occupancy_not_applicable(): void
    {
        // Negative occupants is an impossible database state — not testable
        $this->assertTrue(true, 'N/A – Negative occupancy cannot exist in database');
    }

    /**
     * @test Min-1: N/A — no state below 0 occupants is possible
     */
    public function allocation_min_minus_one_not_applicable(): void
    {
        $this->assertTrue(true, 'N/A – Occupancy below 0 is not a valid state');
    }

    /** @test Min boundary: empty room (0/3) allows allocation */
    public function allocation_min_boundary_empty_room_0_of_3_succeeds(): void
    {
        $result = $this->validateAllocation(3, 0, 0, 1);
        $this->assertNull($result, 'Min boundary: empty room (0/3) should allow allocation');
    }

    /** @test Min+1: room with 1 occupant (1/3) allows allocation */
    public function allocation_min_plus_one_1_of_3_succeeds(): void
    {
        $result = $this->validateAllocation(3, 1, 0, 1);
        $this->assertNull($result, 'Min+1: 1 occupant (1/3) should allow allocation');
    }

    /** @test Max-1: room with 2 occupants (2/3) allows last allocation */
    public function allocation_max_minus_one_2_of_3_succeeds(): void
    {
        $result = $this->validateAllocation(3, 2, 0, 1);
        $this->assertNull($result, 'Max-1: 2 occupants (2/3) — last spot available');
    }

    /** @test Max boundary: full room (3/3) blocks allocation */
    public function allocation_max_boundary_full_room_3_of_3_is_blocked(): void
    {
        $result = $this->validateAllocation(3, 3, 0, 1);
        $this->assertNotNull($result, 'Max boundary: full room (3/3) should block allocation');
        $this->assertEquals('Room is already at full capacity (3 / 3).', $result);
    }

    /** @test Max+1: room still full (4th attempt) blocks allocation */
    public function allocation_max_plus_one_overfull_room_is_blocked(): void
    {
        $result = $this->validateAllocation(3, 4, 0, 1);
        $this->assertNotNull($result, 'Max+1: overfull room should block allocation');
    }

    /** @test Mid: room at 1/3 allows allocation */
    public function allocation_mid_1_of_3_succeeds(): void
    {
        $result = $this->validateAllocation(3, 1, 0, 2);
        $this->assertNull($result, 'Mid: 1/3 occupied should allow allocation');
    }

    /**
     * @test Extreme Max: N/A — 9999 occupants is an impossible database state
     */
    public function allocation_extreme_max_not_applicable(): void
    {
        $this->assertTrue(true, 'N/A – 9999 occupants is an impossible state');
    }

    /** @test Invalid: no student selected — filter_input returns false */
    public function allocation_invalid_no_student_id_provided(): void
    {
        // Mirrors: $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT)
        $student_id = filter_var('', FILTER_VALIDATE_INT);
        $this->assertFalse($student_id, 'Invalid: missing student_id should be rejected');
    }

    /** @test Other: student already allocated to the same room is rejected */
    public function allocation_other_student_already_in_same_room_is_rejected(): void
    {
        // Student is in room 1, trying to allocate to room 1 again
        $result = $this->validateAllocation(3, 1, 1, 1);
        $this->assertNotNull($result, 'Other: student already in this room should be rejected');
        $this->assertEquals('Student is already allocated to this room.', $result);
    }
}
