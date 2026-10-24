<?php

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Livewire\Documents\DocumentList;
use App\Livewire\Documents\UploadDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function fakeDocxContent(): string
{
    return "PK\x03\x04".str_repeat('contenido-docx-', 20);
}

function fakePdfContent(): string
{
    return "%PDF-1.7\n".str_repeat('contenido-pdf-', 20);
}

it('redirige a invitados desde la carga de documentos', function () {
    $this->get('/documents/upload')->assertRedirect(route('login'));
});

it('carga un DOCX privado, calcula el hash y encola el procesamiento', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $content = fakeDocxContent();

    $file = UploadedFile::fake()->createWithContent('tesis final.docx', $content);

    Livewire::actingAs($user)
        ->test(UploadDocument::class)
        ->set('file', $file)
        ->set('consent', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('documents.show', Document::query()->first()));

    $document = Document::query()->first();

    expect($document)->not->toBeNull()
        ->and($document->user_id)->toBe($user->getKey())
        ->and($document->original_name)->toBe('tesis final.docx')
        ->and($document->extension)->toBe('docx')
        ->and($document->status)->toBe(DocumentStatus::Uploaded)
        ->and($document->size_bytes)->toBe(strlen($content))
        ->and($document->sha256)->toBe(hash('sha256', $content))
        ->and($document->metadata['consent'] ?? null)->toBeTrue();

    Storage::disk('local')->assertExists($document->storage_path);

    Queue::assertPushed(
        ProcessDocument::class,
        fn (ProcessDocument $job): bool => $job->documentId === $document->getKey(),
    );

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'document.uploaded',
        'subject_id' => $document->getKey(),
    ]);
});

it('acepta un PDF con firma válida', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('articulo.pdf', fakePdfContent());

    Livewire::actingAs($user)
        ->test(UploadDocument::class)
        ->set('file', $file)
        ->set('consent', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Document::query()->first()?->extension)->toBe('pdf');
});

it('rechaza extensiones no admitidas', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('notas.txt', 'texto plano');

    Livewire::actingAs($user)
        ->test(UploadDocument::class)
        ->set('file', $file)
        ->set('consent', true)
        ->call('save')
        ->assertHasErrors(['file' => 'mimes']);

    expect(Document::query()->count())->toBe(0);
});

it('rechaza un archivo cuya firma real no corresponde al DOCX declarado', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('falso.docx', 'esto no es un docx');

    Livewire::actingAs($user)
        ->test(UploadDocument::class)
        ->set('file', $file)
        ->set('consent', true)
        ->call('save')
        ->assertHasErrors(['file']);

    expect(Document::query()->count())->toBe(0);
});

it('exige la autorización explícita para procesar el documento', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('tesis.docx', fakeDocxContent());

    Livewire::actingAs($user)
        ->test(UploadDocument::class)
        ->set('file', $file)
        ->set('consent', false)
        ->call('save')
        ->assertHasErrors(['consent' => 'accepted']);

    expect(Document::query()->count())->toBe(0);
});

it('rechaza archivos que superan el tamaño máximo configurado', function () {
    Queue::fake();
    Storage::fake('local');
    config(['scitrace.documents.max_kb' => 1]);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent(
        'grande.docx',
        "PK\x03\x04".str_repeat('x', 4096),
    );

    Livewire::actingAs($user)
        ->test(UploadDocument::class)
        ->set('file', $file)
        ->set('consent', true)
        ->call('save')
        ->assertHasErrors(['file' => 'max']);

    expect(Document::query()->count())->toBe(0);
});

it('muestra solo los documentos del usuario autenticado', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Document::factory()->for($user)->create(['original_name' => 'mi-tesis.docx']);
    Document::factory()->for($other)->create(['original_name' => 'tesis-ajena.docx']);

    $this->actingAs($user)
        ->get('/documents')
        ->assertOk()
        ->assertSee('mi-tesis.docx')
        ->assertDontSee('tesis-ajena.docx');
});

it('elimina el archivo privado y aplica borrado lógico al documento', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'storage_path' => 'documents/'.$user->getKey().'/borrar.docx',
    ]);

    Storage::disk('local')->put($document->storage_path, 'contenido');

    Livewire::actingAs($user)
        ->test(DocumentList::class)
        ->call('delete', $document->getKey())
        ->assertHasNoErrors();

    Storage::disk('local')->assertMissing($document->storage_path);
    $this->assertSoftDeleted('documents', ['id' => $document->getKey()]);
});
