
# Application Structure

## Data

### Database

All direct database interaction is handled by class ```\helper\Database```.

### 'Model' layer

All database queries are handled by 'Model' classes, following the [Model View Controller](https://en.wikipedia.org/wiki/Model%E2%80%93view%E2%80%93controller) application architecture.

Each database table has a corresponding class to store SQL queries which are executed via class ```\helper\Database```.

Example: ```\Model\SubscriberModel```

### Entites: data objects

Data objects called 'Entities' are used to pass data around internal application components. These are simple objects with consistent properties mapping to data stored in other locations, such as the database.

Example: ```\Entity\SubscriberEntity```

### Managers: manipulating data

Manager objects perform high-level manipulation of data, including passing data between Model and Entity objects. Manager classes perform the role of Controllers from MVC architecture. This is important for maintaining a consistent interface to data between application components via data objects, and providing flexibility, separately, for database queries in Model classes.

Example: ```\phpList\SubscriberManager```

### In practice

Managers provide a high-level interface to client code for interacting with a particular application component. Client code should need to interact with only a Manager class and an Entity class. A Manager object provides methods to get, process, and save data relating to that component, and the corresponding Entity object provides a consistent format for that data to use as it enters and leaves the Manager.

Example (names may be outdated):

```php
// Instantiate new Manager object
$subscriberManager = new SubscriberManager();
// Retrieve a subscriber by their ID from the data storage layer
$subscriberEntity = $this->subscriberManager->getById( 1 );
// Change a property of the Subscriber
$subscriberEntity->disabled == 0;
// Save the changed Subscriber details to data storage layer
$subscriberManager->save->( $subscriberEntity );
```
