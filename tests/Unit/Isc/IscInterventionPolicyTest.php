<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Models\Isc\IscIntervention;
use App\Models\User;
use App\Policies\Isc\IscInterventionPolicy;
use Mockery;
use Tests\TestCase;

final class IscInterventionPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pic_cannot_verify_own_intervention(): void
    {
        $pic = $this->userMock(10, false, ['isc-pic']);
        $intervention = new IscIntervention(['pic_user_id' => 10]);

        $this->assertFalse((new IscInterventionPolicy())->verify($pic, $intervention));
    }

    public function test_verifier_can_verify_other_pic(): void
    {
        $verifier = $this->userMock(20, false, ['isc-verifier']);
        $intervention = new IscIntervention(['pic_user_id' => 10]);

        $this->assertTrue((new IscInterventionPolicy())->verify($verifier, $intervention));
    }

    public function test_admin_can_verify_if_not_pic(): void
    {
        $admin = $this->userMock(30, true, []);
        $intervention = new IscIntervention(['pic_user_id' => 10]);

        $this->assertTrue((new IscInterventionPolicy())->verify($admin, $intervention));
    }

    public function test_pic_can_create(): void
    {
        $this->assertTrue((new IscInterventionPolicy())->create($this->userMock(10, false, ['isc-pic'])));
    }

    /**
     * @param  list<string>  $roles
     */
    private function userMock(int $id, bool $admin, array $roles): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->shouldReceive('isAdmin')->andReturn($admin);
        $user->shouldReceive('hasRole')->andReturnUsing(
            static fn (string $slug): bool => in_array($slug, $roles, true),
        );

        return $user;
    }
}
