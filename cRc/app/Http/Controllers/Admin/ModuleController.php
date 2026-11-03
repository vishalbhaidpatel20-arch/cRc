<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin-only. Module names are entered here, never hard-coded in the app. */
class ModuleController extends Controller
{
    public function index(): View
    {
        return view('admin.modules.index', [
            'modules' => ProductModule::withCount('contacts')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:product_modules,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        ProductModule::create($data);

        return redirect()->route('admin.modules.index')->with('status', "Module {$data['name']} added.");
    }

    public function update(Request $request, ProductModule $module): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('product_modules', 'name')->ignore($module->module_id, 'module_id')],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $module->update($data);

        return redirect()->route('admin.modules.index')->with('status', 'Module updated.');
    }
}
