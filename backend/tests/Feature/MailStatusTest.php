<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MailStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MailStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_diagnostic_requires_an_administrator(): void
    {
        $this->getJson('/api/system/mail-status')->assertUnauthorized();
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'must_change_password' => false]))
                ->getJson('/api/system/mail-status')->assertForbidden();
        }
    }

    public function test_admin_can_check_web_transport_without_sending_email(): void
    {
        $this->mock(MailStatus::class, function ($mock) {
            $mock->shouldReceive('check')->once()->andReturn(['ok' => true, 'message' => 'SMTP verificado sin envío.']);
        });
        $this->actingAs(User::factory()->create(['role' => 'admin', 'must_change_password' => false]))
            ->getJson('/api/system/mail-status')->assertOk()->assertJsonPath('ok', true);
    }

    public function test_network_block_is_distinguished_from_bad_credentials(): void
    {
        $service = new MailStatus;
        $network = $service->message(new TransportException('stream_socket_client(): An attempt was made to access a socket in a way forbidden by its access permissions'));
        $credentials = $service->message(new TransportException('Failed to authenticate: 535'));
        $this->assertStringContainsString('bloqueada la conexión de red', $network);
        $this->assertStringContainsString('rechazó la autenticación', $credentials);
        $this->assertNotSame($network, $credentials);
    }
}
