ALTER TABLE carts
    ADD UNIQUE INDEX uq_carts_session_id (session_id);
