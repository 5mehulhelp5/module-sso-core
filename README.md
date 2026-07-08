# MageDevGroup_SsoCore

> Shared OIDC engine for the MageDevGroup SSO suite — the single dependency every login product builds on.

![License](https://img.shields.io/badge/license-OSL--3.0-green) ![Magento](https://img.shields.io/badge/Magento-2.4-orange) ![PHP](https://img.shields.io/badge/PHP-8.3--8.5-blue) ![Version](https://img.shields.io/badge/version-0.0.1-lightgrey)

The shared OIDC/SSO engine for the MageDevGroup identity suite. It is installed
transitively by every login product (`admin-sso-okta`, `customer-sso-okta`, ...),
so you rarely require it directly.

## What it provides

Ships **protocol only** — no UI, no config, no secret storage. Products own
configuration and supply a small `ProviderPreset`; the engine does the OIDC work.
Scope v1 is **OIDC** (authorization-code + PKCE); SAML is a later phase.

- Generic OIDC client: discovery, authorization request, token exchange, ID-token
  validation against JWKS, claim → identity normalization.
- A stateless group/claim **mapping engine** (IdP values → target keys).
- A `ProviderPreset` contract so IdP differences (discovery URL, scopes, groups
  claim, branding) stay in a provider plugin module — one engine serves any
  compliant IdP.

## OIDC flow

1. **Discovery** — `DiscoveryClient::discover($url)` fetches (and caches)
   `.well-known/openid-configuration` → `ProviderMetadata` (issuer, authorization,
   token, JWKS endpoints).
2. **Authorization request** — `AuthorizationRequestFactory::create()` builds the
   redirect URL with `state`, `nonce`, and a PKCE `S256` challenge. It returns an
   `AuthorizationRequest` (implements `AuthorizationStateInterface`); the consumer
   persists that state via `AuthorizationStateStorageInterface` before redirecting.
3. **Callback** — the consumer reloads the state with `consume($state)` (one-time;
   replay-protected).
4. **Token exchange** — `TokenClient::exchangeCode()` redeems the code with the PKCE
   verifier at the token endpoint → `TokenResponse` (carries the `id_token`).
5. **ID-token validation** — `JwksClient::getKeySet()` resolves the JWKS;
   `IdTokenValidator::validate()` verifies the JWS signature (asymmetric algorithms
   only — no `none`/`HS*`) and the `iss`/`aud`/`exp`/`nonce` claims → validated claims.
6. **Normalization** — `IdentityFactory::create($claims, $preset)` maps `sub`,
   `email`, `name`, and the preset's groups claim into an `IdentityInterface`.
7. **Mapping** — `MappingEngine::resolve($groups, $rules, $default)` turns IdP groups
   into target keys (e.g. Magento role ids).

`state` guards CSRF, `nonce` guards replay, PKCE binds the code to the client that
started the flow.

## The ProviderPreset contract

`Api/ProviderPresetInterface` is the only IdP-specific surface. A provider plugin
module (e.g. `admin-sso-okta`) implements it and registers the preset into the
capability core's `PresetRegistry`. sso-core itself holds no registry and no
IdP-specific code.

| Method | Purpose |
|---|---|
| `getCode()` | Stable machine code (`okta`, `azure`). |
| `getLabel()` | Human-readable IdP name for the admin UI. |
| `buildDiscoveryUrl(array $config)` | Discovery URL from the product's resolved config. |
| `getDefaultScopes()` | Scopes to request (always includes `openid`). |
| `getGroupsClaim()` | Claim carrying groups (`groups`), or `null`. |
| `getButtonLabel()` | Login-button label. |
| `getButtonIconUrl()` | Login-button icon URL, or `null`. |

## How products consume the engine

Install pulls the core transitively:

```
composer require magedevgroup/module-admin-sso-okta   # pulls magedevgroup/module-sso-core
```

A product wires the flow using only the public surface — build the auth URL from
its preset, validate the ID token, normalize an Identity, map groups:

```php
$authRequest = $authorizationRequestFactory->create(
    $metadata->getAuthorizationEndpoint(),
    $clientId,
    $redirectUri,
    $preset->getDefaultScopes()
);
$stateStorage->save($authRequest);           // persist before redirect
// redirect user to $authRequest->getUrl()

// --- on callback ---
$state  = $stateStorage->consume($stateParam);
$tokens = $tokenClient->exchangeCode(
    $metadata->getTokenEndpoint(),
    $code,
    $redirectUri,
    $clientId,
    $state->getCodeVerifier(),
    $clientSecret
);
$claims = $idTokenValidator->validate(
    $tokens->getIdToken(),
    $jwksClient->getKeySet($metadata->getJwksUri()),
    $metadata->getIssuer(),
    $clientId,
    $state->getNonce()
);
$identity = $identityFactory->create($claims, $preset);
$roles    = $mappingEngine->resolve($identity->getGroups(), $groupRules, $defaultRole);
```

The engine never stores secrets — the product passes already-resolved client
id/secret and owns the `AuthorizationStateStorageInterface` implementation (session,
cache, ...).

## Public API (`Api/`)

Stable contracts products depend on. Full method docs live in the interface files.

| Interface | Role |
|---|---|
| `Api/ProviderPresetInterface` | IdP preset a product supplies to the engine. |
| `Api/Data/IdentityInterface` | Provider-agnostic normalized identity (`getSubjectId`, `getEmail`, `getName`, `getGroups`). |
| `Api/AuthorizationStateStorageInterface` | Product-owned persistence of the one-time auth state (`save`, `consume`). |
| `Api/Data/AuthorizationStateInterface` | The one-time state itself (`getState`, `getNonce`, `getCodeVerifier`). |

## Requirements

- Magento Open Source / Adobe Commerce **2.4.x**
- PHP **8.3 – 8.5**
- `magento/framework >=103.0`
- `web-token/jwt-framework ^4.0`

## Part of the MageDevGroup identity suite

| Repo | Role |
|------|------|
| `sso-core` | Shared OIDC engine (installed automatically) |
| `admin-sso` · `admin-sso-<idp>` | Admin-panel SSO login |
| `customer-sso` · `customer-sso-<idp>` | Storefront SSO login |
| `admin-scim` · `admin-scim-<idp>` | Admin-user provisioning (SCIM 2.0) |

## License

[OSL-3.0](LICENSE) © MageDevGroup. Commercial licensing and support: <https://magedevgroup.com>.
