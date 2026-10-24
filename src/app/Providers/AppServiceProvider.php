<?php

namespace App\Providers;

use App\Livewire\Admin\UsersTable;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Documents\DocumentDetail;
use App\Livewire\Documents\DocumentList;
use App\Livewire\Documents\UploadDocument;
use App\Livewire\Profile\EditProfile;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Document::class, DocumentPolicy::class);

        // Registro explícito para evitar dependencias de autodescubrimiento.
        Livewire::component('auth.login', Login::class);
        Livewire::component('auth.register', Register::class);
        Livewire::component('auth.forgot-password', ForgotPassword::class);
        Livewire::component('auth.reset-password', ResetPassword::class);
        Livewire::component('profile.edit-profile', EditProfile::class);
        Livewire::component('admin.users-table', UsersTable::class);
        Livewire::component('documents.document-list', DocumentList::class);
        Livewire::component('documents.upload-document', UploadDocument::class);
        Livewire::component('documents.document-detail', DocumentDetail::class);
    }
}
