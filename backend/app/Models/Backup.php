<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use HasUuids;

    protected $fillable = ['status', 'archive_path', 'checksum', 'error_message', 'created_by'];
}
