<?php

final class PaypalNvpConfigDto
{
  public string $apiUser;
  public string $apiPassword;
  public string $apiSignature;
  public string $endpoint;
  public string $txtId;

  public function __construct(
    string $endpoint,
    string $apiUser,
    string $apiPassword,
    string $apiSignature,
    string $txtId,
  ) {
    $this->endpoint = $endpoint;
    $this->apiUser = $apiUser;
    $this->apiPassword = $apiPassword;
    $this->apiSignature = $apiSignature;
    $this->txtId = $txtId;
  }
}