# Class structure

All production classes are located in `Classes/`, and all unit and integration
tests are located in `Tests/`.


## Core/

### Bootstrap

This class bootstraps the phpList core system.


## Domain/

### Model/

These classes are the domain models, which map to some of the database tables,
and where the model-related business logic can be found. There must be no
database access code in these classes.

### Repository/

These classes are reponsible for reading domain models from the database,
for writing them there, and for other database queries.


## Security

These classes deal with security-related concerns, e.g., password hashing.


