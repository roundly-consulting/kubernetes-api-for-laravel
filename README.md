<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/kubernetes-api-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=kubernetes-api-for-laravel">
    <img src="art/hero.png" alt="Kubernetes API for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/kubernetes-api-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/kubernetes-api-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/kubernetes-api-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/kubernetes-api-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/kubernetes-api-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/kubernetes-api-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=kubernetes-api-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Kubernetes API for Laravel

A fluent, Eloquent-style client for the Kubernetes API in Laravel. Talk to one or many clusters
through a chainable API built on Laravel's HTTP client, with the core Kubernetes resources,
Traefik CRDs and your own custom resources built in.

## Installation

Requires PHP 8.4, Laravel 12 or 13.

```bash
composer require roundly-consulting/kubernetes-api-for-laravel
```

Point it at your cluster with `KUBERNETES_URL` and `KUBERNETES_TOKEN` in `.env`.

## Usage

List, create, scale and restart resources in one namespace of the default cluster:

```php
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Types\Container;

$shop = Kubernetes::namespace('shop');                       // every request stays in `shop`

$pods = $shop->pods()->whereLabel('app', 'web')->get();      // a collection of Pod resources

$shop->deployments()
    ->setName('checkout')
    ->setReplicas(3)
    ->setPodsSelectors(['app' => 'checkout'])
    ->setTemplate(Pod::make()
        ->setLabels(['app' => 'checkout'])
        ->setContainers([Container::make()->setName('app')->setImage('nginx', '1.27-alpine')]))
    ->create();

$shop->deployments()->withName('checkout')->scale(5);         // via the /scale subresource
$shop->deployments()->withName('checkout')->rolloutRestart(); // like `kubectl rollout restart`
```

Reach any other cluster the same way:

```php
Kubernetes::cluster('production')->nodes()->get();           // a cluster from config/kubernetes.php
Kubernetes::fromKubeConfig(context: 'staging')->pods()->get(); // a kubeconfig context
Kubernetes::ping();                                          // does the apiserver answer?
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/kubernetes-api-for-laravel](https://roundly-consulting.com/open-source/docs/kubernetes-api-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=kubernetes-api-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=kubernetes-api-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=kubernetes-api-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
