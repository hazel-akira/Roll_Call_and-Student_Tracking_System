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

  'roll_call_report' => [
    'auto_send' => filter_var(env('ROLL_CALL_REPORT_AUTO_SEND', true), FILTER_VALIDATE_BOOL),
    'use_microsoft_graph' => filter_var(env('ROLL_CALL_REPORT_USE_MS_GRAPH', false), FILTER_VALIDATE_BOOL),
    'extra_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ROLL_CALL_REPORT_EXTRA_RECIPIENTS', ''))
    ))),
  ],
];
