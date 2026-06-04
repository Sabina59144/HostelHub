HostelHub User Module Formal Test Scripts

Files:
1. TestRunner.php
   - Lightweight testing framework.
   - Collects test results and displays them in Portfolio 2 style.

2. UserValidator.php
   - Contains validation functions for the users table.

3. UserModuleTests.php
   - Runs all boundary tests for each users table data type:
     INTEGER, VARCHAR, ENUM, BOOLEAN/TINYINT, DATETIME.

How to use with XAMPP:
1. Create a folder:
   C:\xampp\htdocs\hostelhub\tests

2. Copy these three PHP files into that folder.

3. Open:
   http://localhost/hostelhub/tests/UserModuleTests.php

4. Take screenshots of the test results and keep them as evidence for Portfolio 2.

Note:
These scripts do not change your database. They test validation logic only.
