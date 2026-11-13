{{-- Requests the data grid code (React + AG Grid, ~1.3 MB) from <head> in parallel with app.js, instead of after it runs. --}}
@if(file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
    {{-- With `npm run dev` the React plugin needs its refresh preamble before grid.jsx loads; it prints nothing in a build. --}}
    @viteReactRefresh
    @vite(['resources/js/grid.jsx'])
@endif
