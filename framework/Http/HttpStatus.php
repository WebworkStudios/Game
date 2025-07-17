<?php
declare(strict_types=1);

namespace Framework\Http;

/**
 * HTTP-Status Codes Enum mit erweiterten Methoden für PHP 8.4
 */
enum HttpStatus: int
{
    // 1xx Informational
    case CONTINUE = 100;
    case SWITCHING_PROTOCOLS = 101;
    case PROCESSING = 102;
    case EARLY_HINTS = 103; // HINZUGEFÜGT: RFC 8297

    // 2xx Success
    case OK = 200;
    case CREATED = 201;
    case ACCEPTED = 202;
    case NON_AUTHORITATIVE_INFORMATION = 203;
    case NO_CONTENT = 204;
    case RESET_CONTENT = 205;
    case PARTIAL_CONTENT = 206;
    case MULTI_STATUS = 207; // HINZUGEFÜGT: WebDAV
    case ALREADY_REPORTED = 208; // HINZUGEFÜGT: WebDAV
    case IM_USED = 226; // HINZUGEFÜGT: RFC 3229

    // 3xx Redirection
    case MULTIPLE_CHOICES = 300;
    case MOVED_PERMANENTLY = 301;
    case FOUND = 302;
    case SEE_OTHER = 303;
    case NOT_MODIFIED = 304;
    case USE_PROXY = 305;
    case TEMPORARY_REDIRECT = 307;
    case PERMANENT_REDIRECT = 308;

    // 4xx Client Error
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case PAYMENT_REQUIRED = 402;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case NOT_ACCEPTABLE = 406;
    case PROXY_AUTHENTICATION_REQUIRED = 407;
    case REQUEST_TIMEOUT = 408;
    case CONFLICT = 409;
    case GONE = 410;
    case LENGTH_REQUIRED = 411;
    case PRECONDITION_FAILED = 412;
    case PAYLOAD_TOO_LARGE = 413;
    case URI_TOO_LONG = 414;
    case UNSUPPORTED_MEDIA_TYPE = 415;
    case RANGE_NOT_SATISFIABLE = 416;
    case EXPECTATION_FAILED = 417;
    case IM_A_TEAPOT = 418; // HINZUGEFÜGT: RFC 2324 (April Fools)
    case PAGE_EXPIRED = 419;
    case MISDIRECTED_REQUEST = 421; // HINZUGEFÜGT: RFC 7540
    case UNPROCESSABLE_ENTITY = 422;
    case LOCKED = 423; // HINZUGEFÜGT: WebDAV
    case FAILED_DEPENDENCY = 424; // HINZUGEFÜGT: WebDAV
    case TOO_EARLY = 425; // HINZUGEFÜGT: RFC 8470
    case UPGRADE_REQUIRED = 426; // HINZUGEFÜGT: RFC 2817
    case PRECONDITION_REQUIRED = 428; // HINZUGEFÜGT: RFC 6585
    case TOO_MANY_REQUESTS = 429;
    case REQUEST_HEADER_FIELDS_TOO_LARGE = 431; // HINZUGEFÜGT: RFC 6585
    case UNAVAILABLE_FOR_LEGAL_REASONS = 451; // HINZUGEFÜGT: RFC 7725

    // 5xx Server Error
    case INTERNAL_SERVER_ERROR = 500;
    case NOT_IMPLEMENTED = 501;
    case BAD_GATEWAY = 502;
    case SERVICE_UNAVAILABLE = 503;
    case GATEWAY_TIMEOUT = 504;
    case HTTP_VERSION_NOT_SUPPORTED = 505; // HINZUGEFÜGT
    case VARIANT_ALSO_NEGOTIATES = 506; // HINZUGEFÜGT: RFC 2295
    case INSUFFICIENT_STORAGE = 507; // HINZUGEFÜGT: WebDAV
    case LOOP_DETECTED = 508; // HINZUGEFÜGT: WebDAV
    case NOT_EXTENDED = 510; // HINZUGEFÜGT: RFC 2774
    case NETWORK_AUTHENTICATION_REQUIRED = 511; // HINZUGEFÜGT: RFC 6585

    /**
     * MODERNISIERT: Typed Class Constants (PHP 8.3+)
     */
    private const array STATUS_TEXTS = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a teapot",
        419 => 'Page Expired',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * MODERNISIERT: Bessere Implementierung mit match
     */
    public function getText(): string
    {
        return self::STATUS_TEXTS[$this->value] ?? 'Unknown Status';
    }

    /**
     * Prüft ob Status-Code successful ist (2xx)
     */
    public function isSuccessful(): bool
    {
        return $this->value >= 200 && $this->value < 300;
    }

    /**
     * Prüft ob Status-Code redirect ist (3xx)
     */
    public function isRedirect(): bool
    {
        return $this->value >= 300 && $this->value < 400;
    }

    /**
     * Prüft ob Status-Code client error ist (4xx)
     */
    public function isClientError(): bool
    {
        return $this->value >= 400 && $this->value < 500;
    }

    /**
     * Prüft ob Status-Code server error ist (5xx)
     */
    public function isServerError(): bool
    {
        return $this->value >= 500 && $this->value < 600;
    }

    /**
     * NEU: Prüft ob Status-Code eine Antwort mit Body erwarten kann
     */
    public function mayHaveBody(): bool
    {
        return match ($this) {
            self::NO_CONTENT, self::RESET_CONTENT, self::NOT_MODIFIED => false,
            default => !$this->isInformational(),
        };
    }

    /**
     * Prüft ob Status-Code informational ist (1xx)
     */
    public function isInformational(): bool
    {
        return $this->value >= 100 && $this->value < 200;
    }

    /**
     * NEU: Prüft ob Status cacheable ist
     */
    public function isCacheable(): bool
    {
        return match ($this) {
            self::OK, self::NON_AUTHORITATIVE_INFORMATION, self::PARTIAL_CONTENT,
            self::MULTIPLE_CHOICES, self::MOVED_PERMANENTLY, self::GONE,
            self::NOT_FOUND, self::METHOD_NOT_ALLOWED, self::UNAVAILABLE_FOR_LEGAL_REASONS => true,
            default => false,
        };
    }
}