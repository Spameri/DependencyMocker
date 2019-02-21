# DependencyMocker
Loads dependencies to mocked classes by Mockery for Nette Framework.

![](https://travis-ci.org/Spameri/DependencyMocker.svg?branch=master "Travis")

## Usage

### Set up

Banned Classes

Data entities, classes with different implementations of mockery magic (e.g. `__getStatic()`) etc.

```
Spamer\DependencyMocker\Mocker::setBannedClasses([
	App\Entity\BaseEntity::class,
	App\GetStatic::class,
]);
```

### Mock Class
```
$basePresenter = Spamer\DependencyMocker\Mocker::mockClassDependencies(App\BasePresenter::class);
```

### Magic

Methods in BasePresenter:
```
$basePresenter->shouldReceive('add')->andReturn(1);
```

Accessing dependencies of BasePresenter and setting up Mockery logic.

#### Private property
```
Spamer\DependencyMocker\Mocker::getProperty(
	App\BasePresenter::class, 
	'articleModel', 
	$basePresenter
)
	->shouldReceive('save')->once();
```

#### Public property
```
$basePresenter->articleModel->shouldReceive('save')->once();
```
