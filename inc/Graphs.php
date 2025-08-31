<?php
namespace KissPlugins\FsmDemo;

class Graphs {
  public static function media_upload() {
    return [
      'states' => ['idle','uploading','processing','failed_retryable','failed_permanent','done'],
      'transitions' => [
        'start' => ['from' => ['idle'], 'to' => 'uploading'],
        'success_upload' => ['from' => ['uploading'], 'to' => 'processing'],
        'success_process' => ['from' => ['processing'], 'to' => 'done'],
        'fail_temp' => ['from' => ['uploading','processing'], 'to' => 'failed_retryable'],
        'retry' => ['from' => ['failed_retryable'], 'to' => 'uploading'],
        'abort' => ['from' => ['idle','uploading','processing'], 'to' => 'failed_permanent'],
        'reset' => ['from' => ['idle','uploading','processing','failed_retryable','failed_permanent','done'], 'to' => 'idle'],
      ]
    ];
  }
}
