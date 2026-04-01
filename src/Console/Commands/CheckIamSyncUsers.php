<?php

namespace Juniyasyos\IamClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckIamSyncUsers extends Command
{
    protected $signature = 'iam:check-sync-users {--d|details : Show row-level details for each user}';
    protected $description = 'Check which users are eligible for IAM sync and report missing fields (email/identifier).';

    public function handle(): int
    {
        $userModelClass = config('iam.user_model', 'App\\Models\\User');
        $userFields = config('iam.user_fields', []);
        $identifierField = config('iam.identifier_field', 'email');

        if (! class_exists($userModelClass)) {
            $this->error("Configured user_model [$userModelClass] does not exist.");
            return 1;
        }

        if (empty($userFields)) {
            $this->warn('iam.user_fields is empty, no attributes will be mapped.');
        }

        $this->info("Using user model: $userModelClass");
        $this->info("Identifier field: $identifierField");

        $query = $userModelClass::query();
        $users = $query->get();

        $total = $users->count();
        $missingIdentifier = 0;
        $missingEmail = 0;
        $ok = 0;

        $rows = [];

        foreach ($users as $user) {
            $identifierValue = data_get($user, $identifierField);
            $email = data_get($user, 'email');

            $hasId = ! empty($identifierValue);
            $hasEmail = ! empty($email);

            if (! $hasId) {
                $missingIdentifier++;
            }

            if (! $hasEmail) {
                $missingEmail++;
            }

            $rowStatus = 'ok';
            if (! $hasId) {
                $rowStatus = 'missing_identifier';
            } elseif (! $hasEmail) {
                $rowStatus = 'missing_email';
            }

            if ($hasId && $hasEmail) {
                $ok++;
            }

            $rows[] = [
                'id' => $user->getKey(),
                $identifierField => $identifierValue,
                'email' => $email,
                'name' => data_get($user, 'name'),
                'status' => $rowStatus,
            ];
        }

        $this->line('');
        $this->info("Total users: $total");
        $this->info("Users with valid identifier ($identifierField): " . ($total - $missingIdentifier));
        $this->info("Users with valid email: " . ($total - $missingEmail));
        $this->info("Fully eligible for sync: $ok");
        $this->line('');

        if ($this->option('details')) {
            $this->table([
                'id',
                $identifierField,
                'email',
                'name',
                'status',
            ], $rows);
        } else {
            $this->table([
                'metric',
                'value',
            ], [
                ['total_users', $total],
                ['missing_identifier', $missingIdentifier],
                ['missing_email', $missingEmail],
                ['eligible', $ok],
            ]);
        }

        if ($missingIdentifier > 0 || $missingEmail > 0) {
            Log::warning('iam.check_sync_users_failed', [
                'missing_identifier' => $missingIdentifier,
                'missing_email' => $missingEmail,
                'total' => $total,
            ]);
        }

        return 0;
    }
}
