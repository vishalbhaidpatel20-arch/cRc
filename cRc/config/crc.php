<?php

return [
    /*
     | Rows the server sends per list page. The data grid (resources/js/grid.jsx) pages through them on screen, so this is
     | large on purpose: a list only falls back to the server's Previous/Next bar when it has more rows than this.
     | Tests set it to 20 (tests/TestCase.php) so paging behaviour stays easy to exercise.
     */
    'per_page' => (int) env('CRC_PER_PAGE', 200),
];
