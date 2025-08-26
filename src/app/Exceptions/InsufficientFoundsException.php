<?php 

class InsufficientFundsException extends Exception {
    protected $message = 'Insufficient chip balance.';
}