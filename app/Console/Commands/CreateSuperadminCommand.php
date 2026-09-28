<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperadminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:create {email : El correo electrónico del superadmin} {--name= : Nombre del superadmin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un usuario superadministrador de plataforma sin membresías de negocio';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $name = trim((string) ($this->option('name') ?: 'Superadministrador'));

        // Validar formato de email
        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'string', 'email', 'max:255']]
        );

        if ($validator->fails()) {
            $this->error('El correo electrónico proporcionado no es válido.');
            return self::FAILURE;
        }

        // Comprobar si el usuario existe y si tiene membresías de negocio
        $existingUser = User::where('email', $email)->first();

        if ($existingUser && $existingUser->businesses()->exists()) {
            $this->error("El correo '{$email}' ya pertenece a un usuario vinculado a un emprendimiento. No se puede convertir en superadmin.");
            return self::FAILURE;
        }

        // Solicitar contraseña oculta con confirmación
        $password = $this->secret('Ingresa la contraseña del superadministrador (mínimo 12 caracteres):');

        if (empty($password) || strlen($password) < 12) {
            $this->error('La contraseña debe tener al menos 12 caracteres.');
            return self::FAILURE;
        }

        $passwordConfirmation = $this->secret('Confirma la contraseña:');

        if ($password !== $passwordConfirmation) {
            $this->error('Las contraseñas no coinciden.');
            return self::FAILURE;
        }

        if ($existingUser) {
            $existingUser->password = Hash::make($password);
            $existingUser->is_superadmin = true;
            $existingUser->must_change_password = false;
            if ($name !== 'Superadministrador' || empty($existingUser->name)) {
                $existingUser->name = $name;
            }
            $existingUser->save();

            $this->info("Usuario existente '{$email}' actualizado y promovido exitosamente a superadministrador.");
        } else {
            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make($password);
            $user->is_superadmin = true;
            $user->must_change_password = false;
            $user->save();

            $this->info("Superadministrador '{$email}' creado exitosamente.");
        }

        return self::SUCCESS;
    }
}
