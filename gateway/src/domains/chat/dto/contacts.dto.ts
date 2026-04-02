import { IsInt, IsOptional, IsString, Matches, Min } from 'class-validator';

/**
 * Pagination parameters for listing contacts of a Uazapi instance.
 */
export class ListContactsDto {
  @IsOptional()
  @IsInt()
  @Min(1)
  page?: number;

  @IsOptional()
  @IsInt()
  @Min(1)
  pageSize?: number;

  @IsOptional()
  @IsInt()
  @Min(0)
  offset?: number;
}

/**
 * Payload for adding a new contact to a Uazapi instance.
 */
export class ContactAddDto {
  @IsString()
  @Matches(/^[\d@.swhatsappnet+()\-\s]{10,30}$/i)
  phone!: string;

  @IsString()
  name!: string;
}

/**
 * Payload for removing a contact from a Uazapi instance.
 */
export class ContactRemoveDto {
  @IsString()
  @Matches(/^[\d@.swhatsappnet+()\-\s]{10,30}$/i)
  phone!: string;
}
