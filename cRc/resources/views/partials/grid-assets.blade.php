{{-- Requests the data grid code (React + AG Grid, ~1.3 MB) from <head> in parallel with app.js, instead of after it runs. --}}
@if(file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
    @vite(['resources/js/grid.jsx'])
@endif
