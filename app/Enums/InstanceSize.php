<?php

namespace App\Enums;

enum InstanceSize: string
{
    case FLEX_C_1VCPU_256MB = 'flex.c-1vcpu-256mb';
    case FLEX_G_1VCPU_512MB = 'flex.g-1vcpu-512mb';
    case FLEX_M_1VCPU_1GB = 'flex.m-1vcpu-1gb';
    case FLEX_C_2VCPU_512MB = 'flex.c-2vcpu-512mb';
    case FLEX_G_2VCPU_1GB = 'flex.g-2vcpu-1gb';
    case FLEX_M_2VCPU_2GB = 'flex.m-2vcpu-2gb';
    case FLEX_C_4VCPU_1GB = 'flex.c-4vcpu-1gb';
    case FLEX_G_4VCPU_2GB = 'flex.g-4vcpu-2gb';
    case FLEX_M_4VCPU_4GB = 'flex.m-4vcpu-4gb';
    case FLEX_C_8VCPU_2GB = 'flex.c-8vcpu-2gb';
    case FLEX_G_8VCPU_4GB = 'flex.g-8vcpu-4gb';
    case FLEX_M_8VCPU_8GB = 'flex.m-8vcpu-8gb';
    case PRO_C_1VCPU_1GB = 'pro.c-1vcpu-1gb';
    case PRO_G_1VCPU_2GB = 'pro.g-1vcpu-2gb';
    case PRO_M_1VCPU_4GB = 'pro.m-1vcpu-4gb';
    case PRO_C_2VCPU_2GB = 'pro.c-2vcpu-2gb';
    case PRO_G_2VCPU_4GB = 'pro.g-2vcpu-4gb';
    case PRO_M_2VCPU_8GB = 'pro.m-2vcpu-8gb';
    case PRO_C_4VCPU_4GB = 'pro.c-4vcpu-4gb';
    case PRO_G_4VCPU_8GB = 'pro.g-4vcpu-8gb';
    case PRO_M_4VCPU_16GB = 'pro.m-4vcpu-16gb';
    case PRO_C_8VCPU_8GB = 'pro.c-8vcpu-8gb';
    case PRO_G_8VCPU_16GB = 'pro.g-8vcpu-16gb';
    case PRO_M_8VCPU_32GB = 'pro.m-8vcpu-32gb';
    case DEDICATED_C_1VCPU_2GB = 'dedicated.c-1vcpu-2gb';
    case DEDICATED_G_1VCPU_4GB = 'dedicated.g-1vcpu-4gb';
    case DEDICATED_M_1VCPU_8GB = 'dedicated.m-1vcpu-8gb';
    case DEDICATED_C_2VCPU_4GB = 'dedicated.c-2vcpu-4gb';
    case DEDICATED_G_2VCPU_8GB = 'dedicated.g-2vcpu-8gb';
    case DEDICATED_M_2VCPU_16GB = 'dedicated.m-2vcpu-16gb';
    case DEDICATED_C_4VCPU_8GB = 'dedicated.c-4vcpu-8gb';
    case DEDICATED_G_4VCPU_16GB = 'dedicated.g-4vcpu-16gb';
    case DEDICATED_M_4VCPU_32GB = 'dedicated.m-4vcpu-32gb';
    case DEDICATED_C_8VCPU_16GB = 'dedicated.c-8vcpu-16gb';
    case DEDICATED_G_8VCPU_32GB = 'dedicated.g-8vcpu-32gb';
    case DEDICATED_M_8VCPU_64GB = 'dedicated.m-8vcpu-64gb';
}
