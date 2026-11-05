{{-- Company form fields, shared by the pop-up on the list page and the stand-alone form page. Expects $company. The fields live in App\Support\ModuleForm. --}}
@include('partials._form-fields', ['module' => 'company', 'model' => $company])
