# PoC: Custom Auth Challenge with Cognito (Impersonation)

## Usage

**Prerequisites:**
- AWS Access
- PHP >8.2 and composer installed
- Symfony CLI installed
- These envs set:

```text
  AWS_REGION=your-region
  AWS_COGNITO_USER_POOL_ID=your-pool-id
  AWS_COGNITO_CLIENT_ID=your-client-id
  AWS_SECRET_NAME=impersonation/secret
```



## Good articles / resources

- [Impersonation using AWS Cognito](https://serverlessfolks.com/impersonation-using-aws-congito)
- [User Impersonation in AWS Cognito](https://medium.com/codex/user-impersonation-in-aws-cognito-dba39219f467)
- [AWS Cognito - Custom Authentication Flow](https://docs.aws.amazon.com/cognito/latest/developerguide/user-pool-lambda-challenge.html) 
