<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Infrastructure;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class CookieBuilder {

	private $expire;
	private $path;
	private $domain;
	private $secure;
	private $httpOnly;
	private $raw;
	private $sameSite;

	public function __construct( int $expire, string $path, ?string $domain, bool $secure, bool $httpOnly, bool $raw, ?string $sameSite ) {
		$this->expire = $expire;
		$this->path = $path;
		$this->domain = $domain;
		$this->secure = $secure;
		$this->httpOnly = $httpOnly;
		$this->raw = $raw;
		$this->sameSite = $sameSite;
	}

	/**
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
	 *
	 * @param string $name
	 * @param string $value
	 * @param int|null $expire
	 * @param string|null $path
	 * @param string|null $domain
	 * @param bool|null $raw
	 * @param string|null $sameSite
	 * @return Cookie
	 */
	public function newCookie( string $name, string $value, ?int $expire = null, ?string $path = null, ?string $domain = null,
								?bool $raw = false, ?string $sameSite = null ): Cookie {
		return new Cookie(
			$name,
			$value,
			$expire ?? $this->expire,
			$path ?? $this->path,
			$domain ?? $this->domain,
			$this->secure,
			$this->httpOnly,
			$raw ?? $this->raw,
			$sameSite ?? $this->sameSite
		);
	}

}
