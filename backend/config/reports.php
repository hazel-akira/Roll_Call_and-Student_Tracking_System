<?php

return [
  /*
   * Roll Call Memo PDF layout (matches institutional roll.pdf template).
   * Boys use numbers boy_start..boy_end; girls use girl_start..girl_end.
   */
  'roll_call_memo' => [
    'boy_roll_start' => (int) env('ROLL_CALL_BOY_START', 1),
    'boy_roll_end' => (int) env('ROLL_CALL_BOY_END', 7),
    'girl_roll_start' => (int) env('ROLL_CALL_GIRL_START', 14),
    'girl_roll_end' => (int) env('ROLL_CALL_GIRL_END', 40),
    'rows_per_page' => (int) env('ROLL_CALL_ROWS_PER_PAGE', 16),
  ],
];
