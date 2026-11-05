{{-- Contact fields, shared by the pop-up, the stand-alone page and the edit page. Expects $contact, $companies, $allModules. The fields live in App\Support\ModuleForm. --}}
@include('partials._form-fields', ['module' => 'contact', 'model' => $contact, 'data' => ['companies' => $companies, 'modules' => $allModules]])
