<?php

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Livewire\Documents\DocumentDetail;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('permite al propietario consultar el detalle de su documento', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create(['original_name' => 'mi-tesis.docx']);

    $this->actingAs($user)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('mi-tesis.docx');
});

it('impide que otro usuario consulte un documento ajeno', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $document = Document::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('documents.show', $document))
        ->assertForbidden();
});

it('no concede acceso documental a los administradores', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $document = Document::factory()->for($owner)->create();

    $this->actingAs($admin)
        ->get(route('documents.show', $document))
        ->assertForbidden();
});

it('redirige a invitados desde el detalle y la lista', function () {
    $document = Document::factory()->create();

    $this->get(route('documents.index'))->assertRedirect(route('login'));
    $this->get(route('documents.show', $document))->assertRedirect(route('login'));
});

it('permite al propietario descargar el archivo privado', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'original_name' => 'articulo.pdf',
        'storage_path' => 'documents/'.$user->getKey().'/articulo.pdf',
        'mime_type' => 'application/pdf',
    ]);

    Storage::disk('local')->put($document->storage_path, '%PDF-1.7 contenido de prueba');

    $response = $this->actingAs($user)->get(route('documents.download', $document));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('x-content-type-options', 'nosniff');

    expect($response->streamedContent())->toContain('%PDF-1.7 contenido de prueba');
});

it('impide descargar documentos ajenos', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $document = Document::factory()->for($owner)->create();

    Storage::disk('local')->put($document->storage_path, 'contenido');

    $this->actingAs($other)
        ->get(route('documents.download', $document))
        ->assertForbidden();
});

it('devuelve 404 al descargar si el archivo físico ya no existe', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('documents.download', $document))
        ->assertNotFound();
});

it('permite reprocesar un documento listo y vuelve a encolar el trabajo', function () {
    Queue::fake();

    $user = User::factory()->create();
    $document = Document::factory()->for($user)->ready()->create();

    Livewire::actingAs($user)
        ->test(DocumentDetail::class, ['document' => $document])
        ->call('reprocess')
        ->assertHasNoErrors();

    Queue::assertPushed(
        ProcessDocument::class,
        fn (ProcessDocument $job): bool => $job->documentId === $document->getKey(),
    );

    expect($document->fresh()->status)->toBe(DocumentStatus::Uploaded);
});

it('no reprocesa un documento que ya está en proceso', function () {
    Queue::fake();

    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'status' => DocumentStatus::Processing,
    ]);

    Livewire::actingAs($user)
        ->test(DocumentDetail::class, ['document' => $document])
        ->call('reprocess');

    Queue::assertNothingPushed();
    expect($document->fresh()->status)->toBe(DocumentStatus::Processing);
});

it('impide reprocesar un documento ajeno', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $document = Document::factory()->for($owner)->ready()->create();

    expect(Gate::forUser($other)->allows('reprocess', $document))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('reprocess', $document))->toBeTrue();
});

it('permite al propietario eliminar el documento desde el detalle', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'storage_path' => 'documents/'.$user->getKey().'/eliminar.docx',
    ]);

    Storage::disk('local')->put($document->storage_path, 'contenido');

    Livewire::actingAs($user)
        ->test(DocumentDetail::class, ['document' => $document])
        ->call('delete')
        ->assertRedirect(route('documents.index'));

    Storage::disk('local')->assertMissing($document->storage_path);
    $this->assertSoftDeleted('documents', ['id' => $document->getKey()]);
});

it('impide eliminar un documento ajeno', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $document = Document::factory()->for($owner)->create();

    expect(Gate::forUser($other)->allows('delete', $document))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $document))->toBeTrue();
});
