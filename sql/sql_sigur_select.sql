SELECT NAME as name, EXPTIME as exptime FROM (
                                      SELECT NAME, EXPTIME, CODEKEY FROM personal
                                      WHERE personal.STATUS != "FIRED"
                                      UNION
                                      SELECT personal.NAME, personal_keys.EXPTIME, personal_keys.CODEKEY FROM personal_keys
                                                                                                                  INNER JOIN personal ON personal_keys.EMP_ID = personal.ID AND personal.STATUS != "FIRED"
                                  ) T1
WHERE T1.CODEKEY = UNHEX(:wildcard)
