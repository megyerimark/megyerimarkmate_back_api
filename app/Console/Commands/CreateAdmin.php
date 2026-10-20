<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create';
    protected $description = 'Create or reset the sole administrator account safely';

    public function handle(): int
    {
        $email = trim((string) $this->ask('Admin e-mail címe'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Érvénytelen e-mail-cím.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('Erős jelszó (legalább 12 karakter)');
        $length = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);
        if ($length < 12) {
            $this->error('A jelszó legyen legalább 12 karakteres.');
            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = 'Megyeri Márk';
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->save();

        $this->info('Adminfiók létrehozva / frissítve.');
        return self::SUCCESS;
    }
}
