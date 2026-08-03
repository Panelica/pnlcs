<?php

use App\Models\Admin;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketEscalation;
use App\Services\TicketEscalationService;

function escalationScopeAdmin(): Admin
{
    return Admin::factory()->create();
}

function escalationScopeDept(string $name, string $email): TicketDepartment
{
    return TicketDepartment::firstOrCreate(['name' => $name], ['email' => $email]);
}

// Bir kuralin kapsami (departman/durum/oncelik) yonetici ekranindan
// girilebilmeli. Girilemezse her kural TUM biletlere uygulanir: yanlis
// departmanin bileti devredilir ve otomatik yanit yanlis musteriye gider.
it('kural kapsami formda secilebiliyor', function () {
    $this->actingAs(escalationScopeAdmin(), 'admin');

    $html = $this->get(route('admin.config.ticket-escalation'))->assertOk()->getContent();

    expect($html)->toContain('name="departments[]"')
        ->and($html)->toContain('name="statuses[]"')
        ->and($html)->toContain('name="priorities[]"');
});

it('kapsam dizi olarak kaydediliyor', function () {
    $this->actingAs(escalationScopeAdmin(), 'admin');

    $dept = escalationScopeDept('Esc Scope Dept', 'esc-scope@test.local');

    $this->post(route('admin.config.ticket-escalation.store'), [
        'name' => 'Kapsamli kural',
        'departments' => [(string) $dept->id],
        'statuses' => ['Open'],
        'priorities' => ['High'],
        'time_elapsed' => 60,
    ])->assertSessionHasNoErrors();

    $rule = TicketEscalation::where('name', 'Kapsamli kural')->firstOrFail();

    expect($rule->departments)->toBe([(string) $dept->id])
        ->and($rule->statuses)->toBe(['Open'])
        ->and($rule->priorities)->toBe(['High']);
});

// Kapsam gercekten sinirlamali: kapsam disindaki bilete dokunulmamali.
it('kapsam disindaki bilet yukseltilmiyor', function () {
    $dept = escalationScopeDept('Esc Scope Dept', 'esc-scope@test.local');
    $other = escalationScopeDept('Esc Scope Other', 'esc-scope2@test.local');

    $inScope = Ticket::create([
        'tid' => 'ESC-IN-'.uniqid(), 'department_id' => $dept->id, 'client_id' => 1,
        'name' => 'In', 'email' => 'in@test.local', 'title' => 'In', 'message' => 'x',
        'status' => 'Open', 'priority' => 'High', 'last_reply' => now()->subDays(2),
    ]);
    $outScope = Ticket::create([
        'tid' => 'ESC-OUT-'.uniqid(), 'department_id' => $other->id, 'client_id' => 1,
        'name' => 'Out', 'email' => 'out@test.local', 'title' => 'Out', 'message' => 'x',
        'status' => 'Open', 'priority' => 'High', 'last_reply' => now()->subDays(2),
    ]);

    TicketEscalation::create([
        'name' => 'Sadece bir departman', 'departments' => [(string) $dept->id],
        'statuses' => ['Open'], 'priorities' => ['High'], 'time_elapsed' => 60,
        'new_priority' => 'High', 'notify' => false,
    ]);

    app(TicketEscalationService::class)->checkAndEscalate();

    expect($inScope->fresh()->escalated_at)->not->toBeNull()
        ->and($outScope->fresh()->escalated_at)->toBeNull();
});
