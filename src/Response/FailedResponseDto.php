<?php

namespace ShieldPpPayment\Response;

class FailedResponseDto
{
  public function __construct(
    public string $msg,
    public ?string $code = null,
    public mixed $data = null,
  ) {
  }
}